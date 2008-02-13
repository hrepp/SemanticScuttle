<?php
require_once 'PHPUnit/Framework.php';

/*
To launch this test, type the following line into a shell
at the root of the scuttlePlus directory :
     phpunit CommonDescriptionTest tests/commonDescriptionTest.php
*/

class CommonDescriptionTest extends PHPUnit_Framework_TestCase
{
    protected $us;
    protected $bs;
    protected $ts;
    protected $tts;
    protected $tsts;
    protected $cds;
 
    protected function setUp()
    {
        global $dbhost, $dbuser, $dbpass, $dbname, $dbport, $dbpersist, $dbtype, $tableprefix;
	require_once('./header.inc.php');

	$this->us =& ServiceFactory::getServiceInstance('UserService');
	$this->bs =& ServiceFactory::getServiceInstance('BookmarkService');
	$this->bs->deleteAll();
	$this->ts =& ServiceFactory::getServiceInstance('TagService');
	$this->ts->deleteAll();
	$this->tts =& ServiceFactory::getServiceInstance('Tag2TagService');
	$this->tts->deleteAll(); 
	$this->tsts =& ServiceFactory::getServiceInstance('TagStatService');
	$this->tsts->deleteAll();
	$this->cds =& ServiceFactory::getServiceInstance('CommonDescriptionService');
	$this->cds->deleteAll();
    }
 
    public function testModifyDescription()
    {
	$cds = $this->cds;

	$uId1 = 1;
	$uId2 = 2;
	$title1 = "title1";
	$title2 = "title2";
	$desc1 = "&é\"'(-è_çà)=´~#'#{{[\\\\[||`\^\^@^@}¹²¡×¿ ?./§µ%";
	$desc2 = "æâ€êþÿûîîôôöŀï'üð’‘ßä«≤»©»  ↓¿×÷¡¹²³";
	$time1 = time();
	$time2 = time()+200;

	$tagDesc1 = array('cdId'=>1, 'tag'=>'taghouse', 'cdDescription'=>$desc1, 'uId'=>$uId1,'cdDatetime'=>$time1);
	$tagDesc2 = array('cdId'=>2, 'tag'=>'taghouse', 'cdDescription'=>$desc2, 'uId'=>$uId2,'cdDatetime'=>$time2);

	$cds->addTagDescription('taghouse', $desc1, $uId1, $time1);
	$cds->addTagDescription('taghouse', $desc2, $uId2, $time2);

	$desc = $cds->getLastTagDescription('taghouse');
	$this->assertContains('taghouse', $desc);
	$this->assertContains($desc2, $desc);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time2), $desc);

	$desc = $cds->getAllTagsDescription('taghouse');
	$this->assertContains($desc1, $desc[1]);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time1), $desc[1]);
	$this->assertContains($desc2, $desc[0]);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time2), $desc[0]);
	
	$desc = $cds->getDescriptionById(1);
	$this->assertContains($desc1, $desc);

	$bkDesc1 = array('cdId'=>3, 'bHash'=>'10', 'cdTitle'=>$title1, 'cdDescription'=>$desc1, 'uId'=>$uId1,'cdDatetime'=>$time1);
	$bkDesc2 = array('cdId'=>4, 'bHash'=>'10', 'cdTitle'=>$title2, 'cdDescription'=>$desc2, 'uId'=>$uId2,'cdDatetime'=>$time2);

	$cds->addBookmarkDescription(10, $title1, $desc1, $uId1, $time1);
	$cds->addBookmarkDescription(10, $title2, $desc2, $uId2, $time2);

	$desc = $cds->getLastBookmarkDescription(10);
	$this->assertContains($title2, $desc);
	$this->assertContains($desc2, $desc);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time2), $desc);

	$desc = $cds->getAllBookmarksDescription(10);
	$this->assertContains($title1, $desc[1]);
	$this->assertContains($desc1, $desc[1]);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time1), $desc[1]);
	$this->assertContains($title2, $desc[0]);
	$this->assertContains($desc2, $desc[0]);
	$this->assertContains(gmdate('Y-m-d H:i:s', $time2), $desc[0]);
	
	$desc = $cds->getDescriptionById(3);
	$this->assertContains($desc1, $desc);


    }

}
?>
