<?php

namespace App\Pages;

use App\Entity\Firm;
use App\Helper as H;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\CheckBox;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;

use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\File;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Panel;
use App\System;

class FirmList extends \App\Pages\Base
{

    private $_firm;

    public function __construct() {
        parent::__construct();

        if (System::getUser()->userlogin != 'admin') {
            System::setErrorMsg(H::l('onlyadminpage'));
            \App\Application::RedirectError();
            return false;
        }
        

        $this->add(new Panel('firmtable'))->setVisible(true);
        $this->firmtable->add(new DataView('firmlist', new \ZCL\DB\EntityDataSource('\App\Entity\Firm', '', 'disabled,firm_name'), $this, 'firmlistOnRow'))->Reload();
        $this->firmtable->add(new ClickLink('addnew'))->onClick($this, 'addOnClick');

      
        $this->add(new Form('firmdetail'))->setVisible(false);
        $this->firmdetail->add(new TextInput('editfirm_name'));
        $this->firmdetail->add(new TextInput('editinn'));
        $this->firmdetail->add(new TextInput('edittin'));
        $this->firmdetail->add(new TextInput('editaddress'));
        $this->firmdetail->add(new TextInput('editphone'));

        $this->firmdetail->add(new CheckBox('editdisabled'));
        $this->firmdetail->add(new TextInput('editlogo'));
        $this->firmdetail->add(new TextInput('editstamp'));
        $this->firmdetail->add(new TextInput('editsign'));
      
        
        $this->firmdetail->add(new SubmitButton('save'))->onClick($this, 'saveOnClick');
        $this->firmdetail->add(new Button('cancel'))->onClick($this, 'cancelOnClick');
        
        $this->add(new Form('keyform'))->setVisible(false);        
        $this->keyform->add(new SubmitButton('send'))->onClick($this,'onSend',true)  ;
        $this->keyform->add(new Button('cancelppo'))->onClick($this, 'cancelOnClick');
        $this->keyform->add(new Button('delppo'))->onClick($this, 'delOnClick');
        $this->keyform->add(new TextInput('password'));
        $this->keyform->add(new DropDownChoice('signtype'))->onChange($this,'onSignType');
        $this->keyform->add(new TextInput('serhost'));
        $this->keyform->add(new TextInput('serport'));
        $this->keyform->add(new CheckBox('usessl'));
        $this->keyform->add(new File('keyfile'));
        $this->keyform->add(new File('certfile'));
     
    }

    public function firmlistOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('firm_name', $item->firm_name));
        $row->add(new Label('ppoowner', $item->ppoowner));

        $row->add(new ClickLink('ppo'))->onClick($this, 'ppoOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
    }

    public function deleteOnClick($sender) {

        $firm_id = $sender->owner->getDataItem()->firm_id;

        $del = Firm::delete($firm_id);
        if (strlen($del) > 0) {
            $this->setError($del);
            return;
        }
        $this->firmtable->firmlist->Reload();
    }


    public function editOnClick($sender) {
        $this->_firm = $sender->owner->getDataItem();
        $this->firmtable->setVisible(false);
        $this->firmdetail->setVisible(true);
        $this->firmdetail->editfirm_name->setText($this->_firm->firm_name);
        $this->firmdetail->editinn->setText($this->_firm->inn);
        $this->firmdetail->edittin->setText($this->_firm->tin);
        $this->firmdetail->editaddress->setText($this->_firm->address);
        $this->firmdetail->editphone->setText($this->_firm->phone);

        $this->firmdetail->editlogo->setText($this->_firm->logo);
        $this->firmdetail->editstamp->setText($this->_firm->stamp);
        $this->firmdetail->editsign->setText($this->_firm->sign);

        $this->firmdetail->editdisabled->setChecked($this->_firm->disabled);
    }

    public function addOnClick($sender) {
        $this->firmtable->setVisible(false);
        $this->firmdetail->setVisible(true);
        // Очищаем  форму
        $this->firmdetail->clean();

        $this->_firm = new Firm();
    }

    public function saveOnClick($sender) {

        $this->_firm->firm_name = $this->firmdetail->editfirm_name->getText();
        $this->_firm->inn = $this->firmdetail->editinn->getText();
        $this->_firm->tin = $this->firmdetail->edittin->getText();
        $this->_firm->address = $this->firmdetail->editaddress->getText();
        $this->_firm->phone = $this->firmdetail->editphone->getText();

        $this->_firm->logo = $this->firmdetail->editlogo->getText();
        $this->_firm->stamp = $this->firmdetail->editstamp->getText();
        $this->_firm->sign = $this->firmdetail->editsign->getText();

        if ($this->_firm->firm_name == '') {
            $this->setError("entername");
            return;
        }

        $this->_firm->disabled = $this->firmdetail->editdisabled->isChecked() ? 1 : 0;

        $this->_firm->save();
        $this->firmdetail->setVisible(false);
        $this->firmtable->setVisible(true);
        $this->firmtable->firmlist->Reload();
    }

    public function cancelOnClick($sender) {
        $this->firmtable->setVisible(true);
        $this->firmdetail->setVisible(false);
        $this->keyform->setVisible(false);
        $this->firmtable->firmlist->Reload();
    }
    public function ppoOnClick($sender) {

      $this->_firm = $sender->owner->getDataItem();
      $this->keyform->setVisible(true);
      $this->firmtable->setVisible(false);
      $this->keyform->password->setText('') ;
      $this->keyform->serhost->setText($this->_firm->ppohost) ;  
      $this->keyform->serport->setText($this->_firm->ppoport) ;  
      $this->keyform->usessl->setChecked($this->_firm->ppousessl) ;  
      
      $this->keyform->delppo->setVisible(strlen($this->_firm->ppoowner)>0) ;
      
      $this->keyform->signtype->setValue($this->_firm->signtype) ;
         
      $this->onSignType($this->keyform->signtype);
      
    } 
    public function delOnClick($sender) {
         
         $this->_firm->ppoowner =  ''  ;
         $this->_firm->ppocert = ''  ;
         $this->_firm->ppokey =  ''  ;
         $this->_firm->save();  
        $this->firmtable->setVisible(true);
        $this->firmdetail->setVisible(false);
        $this->keyform->setVisible(false);
        $this->firmtable->firmlist->Reload();         
               
    }
   
    public function onSignType($sender) {
           $this->keyform->password->setVisible(false);
           $this->keyform->keyfile->setVisible(false);
           $this->keyform->certfile->setVisible(false);
           $this->keyform->serhost->setVisible(false);
           $this->keyform->serport->setVisible(false);
           $this->keyform->usessl->setVisible(false);
       
           if($sender->getValue()==0) {
                 $this->keyform->password->setVisible(true);
                 $this->keyform->keyfile->setVisible(true);
                 $this->keyform->certfile->setVisible(true);
        
           }
           if($sender->getValue()==1) {
                 $this->keyform->serhost->setVisible(true);
                 $this->keyform->serport->setVisible(true);
                 $this->keyform->usessl->setVisible(true);
                 $this->keyform->password->setVisible(true);
                 $this->keyform->keyfile->setVisible(true);
                 $this->keyform->certfile->setVisible(true);
        
           }
           if($sender->getValue()==2) {
                 $this->keyform->serhost->setVisible(true);
                 $this->keyform->serport->setVisible(true);
                 $this->keyform->usessl->setVisible(true);
        
           }
           
           
    }
    public function onSend($sender) {
       $signtype =  $this->keyform->signtype->getValue()  ;
         
       $serhost = $this->keyform->serhost->getText() ;
       $serport = $this->keyform->serport->getText() ;
       $usessl  = $this->keyform->usessl->isChecked() ;
       $password = $this->keyform->password->getText() ;
       $keyfile = $this->keyform->keyfile->getFile() ;
       $certfile = $this->keyform->certfile->getFile() ;
        
     
       if($signtype==0 || $signtype==1) {
           $keydata =  @file_get_contents($keyfile['tmp_name']);
           $certdata =  @file_get_contents($certfile['tmp_name']);
           
           if(strlen($password)==0  || strlen($keydata)==0  || strlen($certdata)==0 )  {
               $this->updateAjax(array(),"   $('#progress').text('". H::l("pponotloaddata") ."');   $('#send').attr('disabled',null);            ") ;
               return;
           }
       }
       if($signtype==0  ) {   //ppolib
      
           try{
             $cert =  \PPOLib\Cert::load($certdata) ;
       
             $key =   \PPOLib\KeyStore::load($keydata,$password,$cert ) ;
             

             $this->_firm->ppoowner =  $cert->getOwner()   ;
             $this->_firm->ppocert = base64_encode(serialize($cert) )  ;
             $this->_firm->ppokey =  base64_encode(serialize($key) )  ;
  
               
           } catch(\Exception $ee){
             $msg = $ee->getMessage() ; 
             $this->updateAjax(array(),"   $('#progress').text('{$msg}');   $('#send').attr('disabled',null);            ") ;
             return;
               
           }      
       
       }
       
       if($signtype==1 || $signtype==2 ) {       
             if(strlen($serhost)==0  || strlen($serport)==0   )  {
                 $this->updateAjax(array(),"   $('#progress').text('". H::l("pponotloaddata") ."');   $('#send').attr('disabled',null);            ") ;
                 return;
             }         
             
             $req = array();
             $req['serversidekey'] = $signtype==2;
             
             if($signtype==1) {
                 
               if(strlen($password)==0  || strlen($keydata)==0  || strlen($certdata)==0 )  {
                   $this->updateAjax(array(),"   $('#progress').text('". H::l("pponotloaddata") ."');   $('#send').attr('disabled',null);            ") ;
                   return;
               }             
               $req['password'] = $password ;
               $req['key'] = base64_encode($keydata);
               $req['cert'] = base64_encode($certdata);
               
              
                 
             }
             
             $json = json_encode($req)   ;
             
             $serhost = rtrim($serhost, '/');

            $request = curl_init();

            curl_setopt_array($request, [
                CURLOPT_PORT           => $serport,
                CURLOPT_URL            => $serhost. ":" .$serport ."/check",
                CURLOPT_POST           => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
              //  CURLOPT_CAINFO => "z:/file.crt",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_SSL_VERIFYPEER   => $usessl==1,
                CURLOPT_POSTFIELDS     => $json
            ]);

            $ret = curl_exec($request);
            if (curl_errno($request) > 0) {
                 $msg = curl_error($request) ; 
                  $msg = str_replace("'","\"",$msg) ;
                 $this->updateAjax(array(),"   $('#progress').text('{$msg}');   $('#send').attr('disabled',null);            ") ;
                 return;            
            }

            curl_close($request);
            $ret = json_decode($ret);
            if ($ret->success==false) {
                 $msg = $ret->error; 
                 if(strlen($ret->message)>0){
                    $msg = $ret->message  ; 
                 }; 
                   $msg = str_replace("'","\"",$msg) ;  
                 $this->updateAjax(array(),"   $('#progress').text('{$msg}');   $('#send').attr('disabled',null);            ") ;
                 return;            
            }  
             
            if($signtype==1) {   //send  key
                 
               
               $this->_firm->ppopassword = $password ;
               $this->_firm->ppokey = base64_encode($keydata);
               $this->_firm->ppocert = base64_encode($certdata);
                
                 
            }          
            $this->_firm->ppoowner =  $ret->owner   ;
            $this->_firm->ppohost =  $serhost  ;
            $this->_firm->ppoport =  $serport   ;
            $this->_firm->ppousessl =  $usessl   ;
          
               
           }
             $this->_firm->pposigntype =  $signtype   ;
             $this->_firm->save();      

             $kl = \App\Helper::l("ppokeyloaded");
             $this->updateAjax(array(),"   $('#progress').text('{$kl}')") ;
         
         
    }
}
