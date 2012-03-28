<?php
class welcome extends Controller
{
    public function index($a)
    {
        echo memory_usage();
        //$this->Asdsa = 'asd';
       $page = $this->load->view('page');
        $page->content = '<br />Not so fast';
        echo "<pre>";
        //$kupon = new kupon();
        $kupon = kupon::all();

        //$kupon[0]->title = 'asdsads';
            var_dump($kupon);
                $page->display();
        echo memory_usage();

    }

    public static function test()
    {
        echo memory_usage();
        $page = Load::view('page');
        $page->content = '<br />STATIC ARE VERY FAST';
        $page->display();
        echo memory_usage();
    }

}

?>