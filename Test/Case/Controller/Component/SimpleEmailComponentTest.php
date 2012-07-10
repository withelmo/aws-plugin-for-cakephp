<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('SimpleEmailComponent', 'Aws.Controller/Component');

class TestSimpleEmailController extends Controller {
    public $paginate = null;
}

class SimpleEmailComponentTest extends CakeTestCase {
    public $SimpleEmailComponent = null;
    public $Controller = null;

    public function setUp() {
        parent::setUp();
        $Collection = new ComponentCollection();
        $this->SimpleEmailComponent = new SimpleEmailComponent($Collection);
        $CakeRequest = new CakeRequest();
        $CakeResponse = new CakeResponse();
        $this->Controller = new TestSimpleEmailController($CakeRequest, $CakeResponse);
        $this->SimpleEmailComponent->startup($this->Controller);
    }

    public function testSubject() {
        $subject = 'This is subject for test.';
        
        $this->assertTrue($this->SimpleEmailComponent->subject($subject));
        $this->assertEqual($this->SimpleEmailComponent->subject, $subject);
    }

    public function tearDown() {
        parent::tearDown();
        unset($this->TestSimpleEmailController);
        unset($this->Controller);
    }
}
