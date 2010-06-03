<?php
/**
* @package     testapp
* @subpackage  jelix_tests module
* @author      Tahina Ramaroson
* @contributor Sylvain de Vathaire
* @contributor Laurent Jouanneau
* @copyright   NEOV 2009
* @link        http://www.jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
* Tests API jCache
* @package     testapp
* @subpackage  jelix_tests module
*/

class UTjCacheAPI extends jUnitTestCaseDb {

    protected $profile;
    
    protected $conf;

    function getTests() {
        $conf = parse_ini_file(JELIX_APP_CONFIG_PATH.'cache.ini.php', true);
        if (isset($conf[$this->profile]) && $conf[$this->profile]['enabled']) {
            $this->conf = $conf[$this->profile];
            return parent::getTests();
        }
        else {
            $this->sendMessage('UTjCacheAPI cannot be run with '.$this->profile.': undefined profile');
            return array();
        }
    }

    public function testSet (){

        $myData=(object)array(
            'id'=>1,
            'content'=>'Lorem ipsum dolor sit amét, conséctetuer adipiscing elit. Donec at odio vitae libero tempus convallis. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Vestibulum purus mauris, dapibus eu, sagittis quis, sagittis quis, mi. Morbi fringilla massa quis velit. Curabitur metus massa, semper mollis, molestie vel, adipiscing nec, massa. Phasellus vitae felis sed lectus dapibus facilisis. In ultrices sagittis ipsum. In at est. Integer iaculis turpis vel magna. Cras eu est. Integer porttitor ligula a tellus. Curabitur accumsan ipsum a velit. Sed laoreet lectus quis leo. Nulla pellentesque molestie ante. Quisque vestibulum est id justo. Ut pellentesque ante in neque.'
        );
        $myObj=(object)array('property1'=>'string','property2'=>'integer');
        $img=@imagecreate(100,100);

        $this->assertFalse(jCache::set('defaultProfileDisabledKey',$myData));

        $this->assertTrue(jCache::set('noExpireKey',$myData,0,$this->profile));
        $this->assertTrue(jCache::get('noExpireKey',$this->profile)==$myData);

        $this->assertFalse(jCache::set('expiredKey','data expired',strtotime("-1 day"),$this->profile));
        $this->assertFalse(jCache::get('expiredKey',$this->profile));


        $this->assertTrue(jCache::set('ttlInDateKey',$myObj,'2020-12-31 00:00:00',$this->profile));
        $this->assertTrue(jCache::get('ttlInDateKey',$this->profile)==$myObj);

        $this->assertTrue(jCache::set('ttlInSecondesKey',$myObj,30,$this->profile));
        $this->assertTrue(jCache::get('ttlInSecondesKey',$this->profile)==$myObj);

        try{
            jCache::set('invalid Key','data for an invalid key',0,$this->profile);
            $this->fail();
        }catch(jException $e){
            $this->pass();
        }

        $this->assertFalse(jCache::set('unableToSerializeDataKey',$img,0,$this->profile));
    }

    public function testGet (){
        jCache::set('getKey','string for data',0,$this->profile);
        jCache::set('expiredKey','data expired',strtotime("-1 day"),$this->profile);

        $data = jCache::get(array('getKey','expiredKey','inexistentKey'),$this->profile);
        $this->assertTrue($data['getKey']=='string for data');
        $this->assertTrue(!isset($data['expiredKey']));
        $this->assertTrue(!isset($data['inexistentKey']));
    }

    public function testCall(){

        jClasses::inc('jelix_tests~testCache');
        $myClass = new testCache();

        $returnData=jCache::call(array('testCache','staticMethod'),array(1,2),0,$this->profile);
        $this->assertTrue($returnData==3);
        $dataCached=jCache::get(md5(serialize(array('testCache','staticMethod')).serialize(array(1,2))),$this->profile);
        $this->assertTrue($dataCached==$returnData);

        try{
            jCache::call(array('testCache','missingStaticMethod'),null,0,$this->profile);
            $this->fail();
        }catch(jException $e){
            $this->pass();
        }

        $returnData=jCache::call(array($myClass,'method'),array(1,2),0,$this->profile);
        $this->assertTrue($returnData==3);
        $dataCached=jCache::get(md5(serialize(array($myClass,'method')).serialize(array(1,2))),$this->profile);
        $this->assertTrue($dataCached==$returnData);

        try{
            jCache::call(array($myClass,'missingMethod'),null,0,$this->profile);
            $this->fail();
        }catch(jException $e){
            $this->pass();
        }

        $returnData=jCache::call('testFunction',array(1,2),0,$this->profile);
        $this->assertTrue($returnData==3);
        $dataCached=jCache::get(md5(serialize('testFunction').serialize(array(1,2))),$this->profile);
        $this->assertTrue($dataCached==$returnData);

        try{
            jCache::call('testFunction_missing',null,0,$this->profile);
            $this->fail();
        }catch(jException $e){
            $this->pass();
        }
    }

    public function testAdd (){

        $ttl=strtotime("+1 day");
        try{
            jCache::add('added1Key',111,$ttl,'invalidProfil');
            $this->fail();
        }catch(jException $e){
            $this->pass();
        }

        jCache::set('existentKey',array((object)array('x'=>0,'y'=>0),'a screen point'),$ttl,$this->profile);
        $this->assertFalse(jCache::add('existentKey','add an existing data',$ttl,$this->profile));
        $this->assertTrue(jCache::add('added1Key',111,$ttl,$this->profile));
        $this->assertTrue(jCache::add('added2Key','some text for example','2020-12-31 00:00:00',$this->profile));
        $this->assertTrue(jCache::add('added3Key','for testing ttl',1,$this->profile));
        $data=jCache::get(array('added1Key','added2Key','added3Key'),$this->profile);
        $this->assertTrue(isset($data['added1Key']) && isset($data['added2Key']) && isset($data['added3Key']));
    }

    public function testIncrement (){
        $this->assertFalse(jCache::increment('InexistentKey',1,$this->profile));

        $this->assertTrue(jCache::set('integerDataKey',100,1,$this->profile));
        $this->assertTrue(jCache::increment('integerDataKey',1,$this->profile)==101);

        $this->assertTrue(jCache::set('floatDataKey',100.5,1,$this->profile));
        $this->assertTrue(jCache::increment('floatDataKey',1,$this->profile)==101);

        $this->assertTrue(jCache::set('floatIncrementationKey',100,1,$this->profile));
        $this->assertTrue(jCache::increment('floatIncrementationKey',1.5,$this->profile)==101);

        $this->assertTrue(jCache::set('stringIncrementationKey',1,1,$this->profile));
        $this->assertFalse(jCache::increment('stringIncrementationKey','increment by string',$this->profile));

        $this->assertTrue(jCache::set('stringDataKey','string data',1,$this->profile));
        $this->assertFalse(jCache::increment('stringDataKey',100,$this->profile));

        $this->assertTrue(jCache::set('arrayDataKey',array(1),1,$this->profile));
        $this->assertFalse(jCache::increment('arrayDataKey',1,$this->profile));

        $oData=(object)array('property1'=>'string','property2'=>1);
        $this->assertTrue(jCache::set('objectDataKey',$oData,1,$this->profile));
        $this->assertFalse(jCache::increment('objectDataKey',1,$this->profile));

    }

    public function testDecrement (){

        $this->assertFalse(jCache::decrement('InexistentKey',1,$this->profile));

        $this->assertTrue(jCache::set('integerDataKey',100,1,$this->profile));
        $this->assertTrue(jCache::decrement('integerDataKey',1,$this->profile)==99);

        $this->assertTrue(jCache::set('floatDataKey',100.5,1,$this->profile));
        $this->assertTrue(jCache::decrement('floatDataKey',1,$this->profile)==99);

        $this->assertTrue(jCache::set('floatDecrementationKey',100,1,$this->profile));
        $this->assertTrue(jCache::decrement('floatDecrementationKey',1.5,$this->profile)==99);

        $this->assertTrue(jCache::set('stringDecrementationKey',1,1,$this->profile));
        $this->assertFalse(jCache::decrement('stringDecrementationKey','decrement by string',$this->profile));

        $this->assertTrue(jCache::set('stringDataKey','string data',1,$this->profile));
        $this->assertFalse(jCache::decrement('stringDataKey',100,$this->profile));

        $this->assertTrue(jCache::set('arrayDataKey',array(1),1,$this->profile));
        $this->assertFalse(jCache::decrement('arrayDataKey',1,$this->profile));

        $oData=(object)array('property1'=>'string','property2'=>1);
        $this->assertTrue(jCache::set('objectDataKey',$oData,1,$this->profile));
        $this->assertFalse(jCache::decrement('objectDataKey',1,$this->profile));

    }

    public function testReplace (){

        $newData = 'data to replace';

        jCache::set('replace1Key','data one',0,$this->profile);
        jCache::set('replace2Key','data two',0,$this->profile);

        $this->assertFalse(jCache::replace('replace3Key',$newData,0,$this->profile));
        $this->assertTrue(jCache::replace('replace1Key',$newData,0,$this->profile));
        $this->assertTrue(jCache::get('replace1Key',$this->profile)==$newData);
        $this->assertTrue(jCache::replace('replace2Key',$newData,strtotime("-1 day"),$this->profile));
        $this->assertFalse(jCache::get('replace2Key',$this->profile));

    }

    public function testDelete (){

        jCache::set('deleteKey','data to delete',0,$this->profile);

        $this->assertTrue(jCache::delete('deleteKey',$this->profile));
        $this->assertFalse(jCache::get('deleteKey',$this->profile));
        $this->assertFalse(jCache::delete('inexistentKey',$this->profile));

    }

    public function testGarbage (){

        $this->assertFalse(jCache::garbage());

        jCache::set('remainingDataKey','remaining data',0,$this->profile);
        jCache::set('garbage1DataKey','data send to the garbage',1,$this->profile);
        jCache::set('garbage2DataKey','other data send to the garbage',strtotime("-1 day"),$this->profile);

        sleep(2);

        $this->assertTrue(jCache::garbage($this->profile));
    }

    public function testFlush (){

        $this->assertFalse(jCache::flush());

        jCache::set('flush1DataKey','some data',0,$this->profile);
        jCache::set('flush2DataKey','data to remove',strtotime("+1 day"),$this->profile);
        jCache::set('flush3DataKey','other data to remove',time()+30,$this->profile);
    }
}