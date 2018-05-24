<?php

namespace App\Conversations;

use App\Answer;
use App\Question;
use App\Highscore;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Messages\Incoming\Answer as BotManAnswer;
use BotMan\BotMan\Messages\Outgoing\Question as BotManQuestion;

class QuizConversation extends Conversation
{
    /** @var Question */
    protected $quizQuestions;

    /** @var integer */
    protected $userPoints = 0;

    /** @var integer */
    protected $userCorrectAnswers = 0;

    /** @var integer */
    protected $questionCount;

    /** @var integer */
    protected $currentQuestion = 1;

    /**
     * Start the conversation.
     *
     * @return mixed
     */
    public function run()
    {
        $this->quizQuestions = Question::all()->shuffle();
        $this->questionCount = $this->quizQuestions->count();
        $this->quizQuestions = $this->quizQuestions->keyBy('id');
        $this->showInfo();
    }

    private function showInfo()
    {
        $this->bot->reply('You will be shown '.$this->questionCount.' questions about Laravel. Every correct answer will reward you with a certain amount of points. Please keep it fair, and don\'t use any help. All the best! 🍀');
        $this->checkForNextQuestion();
    }

    private function checkForNextQuestion()
    {
        if ($this->quizQuestions->count() > 0) {
            $this->askQuestion($this->quizQuestions->first());
        } else {
            $this->showResult();
        }
    }

    private function askQuestion(Question $question)
    {
        $this->ask($this->createQuestionTemplate($question), function (BotManAnswer $answer) use ($question) {
            $quizAnswer = Answer::find($answer->getValue());

            if (! $quizAnswer) {
                $this->bot->reply('Sry I did not get that. Please use the buttons.');
                $this->checkForNextQuestion();
            } else {
                $this->quizQuestions->forget($question->id);

                if ($quizAnswer->correct_one) {
                    $this->userPoints += $question->points;
                    $this->userCorrectAnswers++;
                    $answerResult = ' ✅';
                } else {
                    $correctAnswer = $question->answers()->where('correct_one', true)->first()->text;
                    $answerResult = ' ❌ (Correct: '.$correctAnswer.')';
                }
                $this->currentQuestion++;

                $this->bot->reply('Your answer: '.$quizAnswer->text.$answerResult);
                $this->checkForNextQuestion();
            }
        });
    }

    private function showResult()
    {
        $this->bot->reply('Finished 🏁');
        $this->bot->reply('You made it through all the questions. You reached '.$this->userPoints.' points! Correct answers: '.$this->userCorrectAnswers.' / '.$this->questionCount);

        $this->askAboutHighscore();
    }

    private function askAboutHighscore()
    {
        $question = BotManQuestion::create('Do you want to get added to the highscore list? Only your latest result will be saved. To achieve that, we need to store your name and chat id.')
            ->addButtons([
                Button::create('Yes please')->value('yes'),
                Button::create('No')->value('no'),
            ]);

        $this->ask($question, function (BotManAnswer $answer) {
            if ($answer->getValue() === 'yes') {
                $user = Highscore::saveUser($this->bot->getUser(), $this->userPoints, $this->userCorrectAnswers);
                $this->bot->reply('Done. Your rank is '.$user->getRank().'.');
                $this->bot->startConversation(new HighscoreConversation());
            } elseif ($answer->getValue() === 'no') {
                $this->bot->reply('Not problem. You were not added to the highscore. Still you can tell your friends about it 😉');
            } else {
                $this->repeat('Sorry, I did not get that. Please use the buttons.');
            }
        });
    }

    private function createQuestionTemplate(Question $question)
    {
        $questionText = '➡️ Question: '.$this->currentQuestion.' / '.$this->questionCount.' : '.$question->text;
        $questionTemplate = BotManQuestion::create($questionText);
        $answers = $question->answers->shuffle();

        foreach ($answers as $answer) {
            $questionTemplate->addButton(Button::create($answer->text)->value($answer->id));
        }

        return $questionTemplate;
    }
}
