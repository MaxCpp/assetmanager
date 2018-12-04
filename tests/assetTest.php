<?php
/**
 * Before running these tests, you must install the package using Repoman
 * and seed the database with the test data!
 *
 *  php repoman.php install /path/to/repos/assetmanager
 *
 * That will ensure that the database tables contain the correct test data.
 * If you need to create more test data, make sure you add the appropriate
 * arrays to the model/seeds/test directory (either manually or via repoman's
 * export command).
 *
 * To run these tests, pass the test directory as the 1st argument to phpunit:
 *
 *   phpunit path/to/core/components/assetmanager/tests
 *
 * or if you're having any trouble running phpunit, download its .phar file, and
 * then run the tests like this:
 *
 *  php phpunit.phar path/to/assetmanager/core/components/assetmanager/tests
 *
 * See http://forums.modx.com/thread/91009/xpdo-validation-rules-executing-prematurely#dis-post-498398
 */

namespace Assman;

use PHPUnit\Framework\TestCase;

class assetTest extends TestCase
{

    // Must be static because we set it up inside a static function
    public static $modx;
    public static $Asset;
    public static $PageAsset;
    public static $Page;

    /**
     * Load up MODX for our tests.
     *
     */
    public static function setUpBeforeClass()
    {
        self::$modx = new \modX();
        self::$modx->initialize('mgr');
        $core_path = self::$modx->getOption('assman.core_path', '', MODX_CORE_PATH . 'components/assetmanager/');
        self::$modx->addExtensionPackage('assman', "{$core_path}model/orm/", array('tablePrefix' => 'ass_'));
        self::$modx->addPackage('assman', "{$core_path}model/", 'ass_');

        // Create Page
        if (!self::$Page = self::$modx->getObject('modResource', array('alias' => 'test-test-test'))) {
            self::$Page = self::$modx->newObject('modResource');
            self::$Page->fromArray(array(
                'alias' => 'test-test-test',
                'pagetitle' => 'Test Asset 101',
            ));
            self::$Page->save();
        }
    }

    /**
     *
     */
    public static function tearDownAfterClass()
    {
        //self::$Asset->remove();
        self::$Page->remove();
    }


    public function testFromFile()
    {
        $A = self::$modx->newObject('Asset');
        $file = dirname(__FILE__) . '/assets/image.jpg';
        $this->assertTrue(file_exists($file));
        $newfile = dirname(__FILE__) . '/assets/image2.jpg';
        if (!file_exists($newfile)) {
            $result = copy($file, $newfile);
            $this->assertTrue($result, 'Failed copying ' . $file . ' to ' . $newfile);
            $this->assertTrue(file_exists($newfile));
        }
        $finfo = new \finfo(FILEINFO_MIME);

        $FILE = array(
            'name' => basename($newfile),
            'tmp_name' => $newfile,
            'type' => $finfo->file($newfile),
            'size' => filesize($newfile)
        );

        $Asset = $A->fromFile($FILE);

        $this->assertTrue(is_object($Asset));

        $this->assertTrue(file_exists($Asset->get('path')));


        // Test thumbnail_manual_url
        $actual = $Asset->get('thumbnail_manual_url');
        $this->assertEquals('', $actual);

        $override = 'http://craftsmancoding.com/assets/test.jpg';
        $Asset->set('thumbnail_manual_url', $override);
        $Asset->save();
        $actual = $Asset->get('thumbnail_url');
        $this->assertEquals($override, $actual);

        $override = '/assets/test.jpg';
        $Asset->set('thumbnail_manual_url', $override);
        $Asset->save();
        $actual = $Asset->get('thumbnail_url');
        $this->assertEquals(MODX_SITE_URL . ltrim($override, '/'), $actual);

        $override = 'http://craftsmancoding.com/assets/test.jpg';
        $Asset->set('manual_url', $override);
        $Asset->save();
        $actual = $Asset->get('url');
        $this->assertEquals($override, $actual);

        // Test normal thumbnail behavior        
        $Asset->set('thumbnail_manual_url', '');
        $actual = $Asset->get('thumbnail_url');
        $this->assertNotEmpty($actual);

        $Asset->remove();
        @unlink($newfile);
    }

    public function testRelation()
    {
        $A = self::$modx->newObject('Asset');
        $file = dirname(__FILE__) . '/assets/image.jpg';
        $this->assertTrue(file_exists($file));
        $newfile = dirname(__FILE__) . '/assets/image2.jpg';
        $result = copy($file, $newfile);
        $this->assertTrue($result, 'Failed copying ' . $file . ' to ' . $newfile);
        $this->assertTrue(file_exists($newfile));
        $finfo = new \finfo(FILEINFO_MIME);

        $FILE = array(
            'name' => basename($newfile),
            'tmp_name' => $newfile,
            'type' => $finfo->file($newfile),
            'size' => filesize($newfile)
        );

        $Asset = $A->fromFile($FILE);

        $asset_id = $Asset->get('asset_id');
        $this->assertTrue((bool)$asset_id);
        $page_id = self::$Page->get('id');
        $this->assertTrue((bool)$page_id);

        if (!$PageAsset = self::$modx->getObject('PageAsset', array('page_id' => $page_id, 'asset_id' => $asset_id))) {
            $PageAsset = self::$modx->newObject('PageAsset');
        }

        $PageAsset->set('page_id', $page_id);
        $PageAsset->set('asset_id', $asset_id);
        $result = $PageAsset->save();
        $this->assertTrue($result);

        $result = $Asset->remove();

        $this->assertTrue($result);

        // Did the PageAsset also get deleted?
        $PageAsset = self::$modx->getObject('PageAsset', array('page_id' => $page_id, 'asset_id' => $asset_id));
        $this->assertTrue(empty($PageAsset));
    }


}