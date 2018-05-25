<?php

namespace Tests\BotMan;

use App\Answer;
use App\Question;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;

class QuizConversationTest extends TestCase
{

    use RefreshDatabase;

    protected function setUp()
    {
        parent::setUp();

        Question::truncate();
        Answer::truncate();

        Question::create([
            'text' => 'Who created Laravel?',
            'points' => 100,
        ]);

        Answer::insert([
            [
                'question_id' => 1,
                'text' => 'Taylor',
                'correct_one' => true,
            ],
            [
                'question_id' => 1,
                'text' => 'Christoph',
                'correct_one' => false,
            ],
        ]);
    }

    /**
     * @test
     **/
    public function it_shows_info_and_incorrect_answer_reply_result_and_no_highscore()
    {
        $possibleQuestionTemplates = $this->getQuestionTemplates();

        $this->bot->receives('/startquiz')
            ->assertReply('You will be shown 1 questions about Laravel. Every correct answer will reward you with a certain amount of points. Please keep it fair, and don\'t use any help. All the best! 🍀')
            ->assertTemplateIn($possibleQuestionTemplates)->receives('2')
            ->assertReply('Your answer: Christoph ❌ (Correct: Taylor)')
            ->assertReply('Finished 🏁')
            ->assertReply('You made it through all the questions. You reached 0 points! Correct answers: 0 / 1')
            ->assertTemplate($this->getAksAboutHighscoreTemplate(), true)->receives('no')->assertReply('Not problem. You were not added to the highscore. Still you can tell your friends about it 😉');
    }

    /**
     * @test
     **/
    public function it_shows_info_and_correct_answer_reply_result_and_highscore()
    {
        list($question1, $question2) = $this->getQuestionTemplates();


        $this->bot->receives('/startquiz')
            ->assertReply('You will be shown 1 questions about Laravel. Every correct answer will reward you with a certain amount of points. Please keep it fair, and don\'t use any help. All the best! 🍀')
            ->assertTemplateIn([$question1, $question2])->receives('1')->assertReply('Your answer: Taylor ✅')
            ->assertReply('Finished 🏁')
            ->assertReply('You made it through all the questions. You reached 100 points! Correct answers: 1 / 1')
            ->assertTemplate($this->getAksAboutHighscoreTemplate(), true)->receives('yes')->assertReply('Done. Your rank is 1.')
            ->assertReply('Here is the current highscore. Do you think you can do better? Start the quiz: /startquiz.');
    }

    /**
     * @return array
     */
    protected function getQuestionTemplates()
    {
        $question1 = \BotMan\BotMan\Messages\Outgoing\Question::create('➡️ Question: 1 / 1 : Who created Laravel?')
            ->addButtons([
                Button::create('Taylor')->value(1),
                Button::create('Christoph')->value(2),
            ]);

        $question2 = \BotMan\BotMan\Messages\Outgoing\Question::create('➡️ Question: 1 / 1 : Who created Laravel?')
            ->addButtons([
                Button::create('Christoph')->value(2),
                Button::create('Taylor')->value(1),
            ]);

        return [$question1, $question2];
    }

    protected function getAksAboutHighscoreTemplate()
    {
        return  \BotMan\BotMan\Messages\Outgoing\Question::create('Do you want to get added to the highscore list? Only your latest result will be saved. To achieve that, we need to store your name and chat id.')
            ->addButtons([
                Button::create('Yes please')->value('yes'),
                Button::create('No')->value('no'),
            ]);
    }

}
