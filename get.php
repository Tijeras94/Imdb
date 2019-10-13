<?php
include 'imdb.php';

if(!isset($_GET['i']) or empty($_GET['i']))
 {
    header('Access-Control-Allow-Origin: *');  
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'error', 'msg'=> 'invalid number of args :('));
    exit();
 }


preg_match_all("~tt(\d+)~", @$_GET['i'], $aMatches);
if ($aMatches === false || is_null($aMatches[0]) || empty($aMatches[0][0])) {

    // args is not an imdb id, do a search instead
    $search = IMDB::search(@$_GET['i']);
    if(count($search)> 0)
    {
        $c = new IMDB("https://www.imdb.com/title/". $search[0]['imdb'] . "/reference");
        header('Access-Control-Allow-Origin: *');  
        header('Content-Type: application/json');
        echo json_encode($c->fetch());
        exit();
    }
}else
{
    // https://www.imdb.com/title/tt9432978/reference

    if(!isset($_GET['season']) or empty($_GET['season']))
    {
        $c = new IMDB("https://www.imdb.com/title/". $aMatches[0][0] . "/reference");
        header('Access-Control-Allow-Origin: *');  
        header('Content-Type: application/json');
        echo json_encode($c->fetch());
    }else
    {
        if(!isset($_GET['episode']) or empty($_GET['episode']))
        {
            header('Access-Control-Allow-Origin: *');  
            header('Content-Type: application/json');
            echo json_encode(IMDB::getEpisodes($aMatches[0][0], $_GET['season']));
        }else
        {
            $eps = IMDB::getEpisodes($aMatches[0][0], $_GET['season']);
            $e = $eps['episodes'][intval($_GET['episode'])];

            //fet episode data
            $c = new IMDB("https://www.imdb.com/title/". $e['imdb'] . "/reference");
            header('Access-Control-Allow-Origin: *');  
            header('Content-Type: application/json');
            echo json_encode($c->fetch());
        }
    }

    

    //var_dump($c->fetch());
    //var_dump($c->search("home"));
}
?>