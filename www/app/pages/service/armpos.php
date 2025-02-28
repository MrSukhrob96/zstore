<?php

namespace App\Pages\Service;

use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\Category;
use App\Entity\Service;
use Zippy\Html\Image;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\DataList\ArrayDataSource;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextArea;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Panel;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;

/**
 * АРМ кассира
 */
class ARMPos extends \App\Pages\Base
{

    public  $_itemlist   = array();
    public  $_serlist    = array();
    private $pos;
    private $_doc        = null;
    private $_rowid      = 0;
    private $_pt         = 0;
    private $_store_id   = 0;
    private $_salesource = 0;
    public  $_catlist  = array();
    public  $_prodlist = array();

    public $_doclist = array();

    public function __construct() {
        parent::__construct();

        if (false == \App\ACL::checkShowSer('ARMPos')) {
            return;
        }
        $filter = \App\Filter::getFilter("armpos");
        if ($filter->isEmpty()) {
            $filter->pos = 0;
            $filter->store = H::getDefStore();
            $filter->pricetype = H::getDefPriceType();
            $filter->salesource = H::getDefSaleSource();


        }

        //обшие настройки
        $this->add(new Form('form1'));
        $plist = \App\Entity\Pos::findArray('pos_name', '');

        $this->form1->add(new DropDownChoice('pos', $plist, $filter->pos));
        $this->form1->add(new DropDownChoice('store', \App\Entity\Store::getList(), $filter->store));
        $this->form1->add(new DropDownChoice('pricetype', \App\Entity\Item::getPriceTypeList(), $filter->pricetype));
        $this->form1->add(new DropDownChoice('salesource', H::getSaleSources(), $filter->salesource));

        $this->form1->add(new SubmitButton('next1'))->onClick($this, 'next1docOnClick');


        $this->add(new Panel('checklistpan'))->setVisible(false);
        $this->add(new ClickLink('newcheck', $this, 'newdoc'));
        $this->checklistpan->add(new DataView('checklist', new ArrayDataSource($this, '_doclist'), $this, 'onDocRow'));

        //панель статуса,  просмотр
        $this->checklistpan->add(new Form('searchform'))->onSubmit($this, 'updatechecklist');
        $this->checklistpan->searchform->add(new AutocompleteTextInput('searchcust'))->onText($this, 'OnAutoCustomer');
        $this->checklistpan->searchform->add(new TextInput('searchnumber', $filter->searchnumber));

        $this->checklistpan->add(new Panel('statuspan'))->setVisible(false);

        $this->checklistpan->statuspan->add(new \App\Widgets\DocView('docview'))->setVisible(false);


        $this->add(new Panel('docpanel'))->setVisible(false);
        $this->docpanel->add(new ClickLink('tochecklist', $this, 'onCheckList'));


        $this->docpanel->add(new Form('form2'))->setVisible(false);

        //  ввод товаров

        $this->docpanel->form2->add(new SubmitButton('topay'))->onClick($this, 'topayOnClick');
        $this->docpanel->form2->add(new TextInput('barcode'));
        $this->docpanel->form2->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');
        $this->docpanel->form2->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docpanel->form2->add(new SubmitLink('addser'))->onClick($this, 'addserOnClick');
        $this->docpanel->form2->addser->setVisible(Service::findCnt('disabled<>1') > 0);  //показываем  если  есть  услуги
        $this->docpanel->form2->add(new Label('total'));

        $this->docpanel->form2->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'));
        $this->docpanel->form2->add(new DataView('detailser', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_serlist')), $this, 'serOnRow'));
        $this->docpanel->form2->add(new ClickLink('openshift', $this, 'OnOpenShift'));
        $this->docpanel->form2->add(new ClickLink('closeshift', $this, 'OnCloseShift'));

        //оплата
        $this->docpanel->add(new Form('form3'))->setVisible(false);
        $this->docpanel->form3->add(new DropDownChoice('payment', \App\Entity\MoneyFund::getList(), H::getDefMF()));

        $this->docpanel->form3->add(new TextInput('document_number'));

        $this->docpanel->form3->add(new Date('document_date'))->setDate(time());
        $this->docpanel->form3->add(new TextArea('notes'));
        $this->docpanel->form3->add(new SubmitLink('addcust'))->onClick($this, 'addcustOnClick');

        $this->docpanel->form3->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');
        $this->docpanel->form3->customer->onChange($this, 'OnChangeCustomer');
        $this->docpanel->form3->add(new Button('cancel2'))->onClick($this, 'cancel2docOnClick');
        $this->docpanel->form3->add(new SubmitButton('save'))->onClick($this, 'savedocOnClick');
        $this->docpanel->form3->add(new TextInput('total2'));
        $this->docpanel->form3->add(new TextInput('paydisc'));
        $this->docpanel->form3->add(new TextInput('payamount'));
        $this->docpanel->form3->add(new TextInput('payed'));
        $this->docpanel->form3->add(new TextInput('exchange'));

        $this->docpanel->form3->add(new Label('discount'));
        //печать
        $this->docpanel->add(new Form('formcheck'))->setVisible(false);
        $this->docpanel->formcheck->add(new Label('showcheck'));
        $this->docpanel->formcheck->add(new Button('newdoc'))->onClick($this, 'newdoc');
        $this->docpanel->formcheck->add(new Button('print'));

        $this->docpanel->add(new Form('editdetail'))->setVisible(false);
        $this->docpanel->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->docpanel->editdetail->add(new TextInput('editprice'));
        $this->docpanel->editdetail->add(new TextInput('editserial'));

        $this->docpanel->editdetail->add(new AutocompleteTextInput('edittovar'))->onText($this, 'OnAutoItem');
        $this->docpanel->editdetail->edittovar->onChange($this, 'OnChangeItem', true);

        $this->docpanel->editdetail->add(new Label('qtystock'));
        $this->docpanel->editdetail->add(new ClickLink('openitemsel', $this, 'onOpenItemSel'));
        $this->docpanel->editdetail->add(new ClickLink('opencatpan', $this, 'onOpenCatPan'));
        
        
        
        $this->docpanel->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->docpanel->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');

        $this->docpanel->add(new \App\Widgets\ItemSel('wselitem', $this, 'onSelectItem'))->setVisible(false);
        $this->docpanel->add(new Panel('catpan'))->setVisible(false);
        $this->docpanel->catpan->add(new DataView('catlist', new ArrayDataSource($this, '_catlist'), $this, 'onCatRow'));

        $this->docpanel->add(new Panel('prodpan'))->setVisible(false);
        $this->docpanel->prodpan->add(new DataView('prodlist', new ArrayDataSource($this, '_prodlist'), $this, 'onProdRow'));
        
        
        
        
        
        $this->docpanel->add(new Form('editserdetail'))->setVisible(false);
        $this->docpanel->editserdetail->add(new TextInput('editserquantity'))->setText("1");
        $this->docpanel->editserdetail->add(new TextInput('editserprice'));

        $this->docpanel->editserdetail->add(new AutocompleteTextInput('editser'))->onText($this, 'OnAutoSer');
        $this->docpanel->editserdetail->editser->onChange($this, 'OnChangeSer', true);

        $this->docpanel->editserdetail->add(new Button('cancelser'))->onClick($this, 'cancelrowOnClick');
        $this->docpanel->editserdetail->add(new SubmitButton('submitser'))->onClick($this, 'saveserOnClick');

        //добавление нового контрагента
        $this->add(new Form('editcust'))->setVisible(false);
        $this->editcust->add(new TextInput('editcustname'));
        $this->editcust->add(new TextInput('editphone'));
        $this->editcust->add(new Button('cancelcust'))->onClick($this, 'cancelcustOnClick');
        $this->editcust->add(new SubmitButton('savecust'))->onClick($this, 'savecustOnClick');

        /*
          //Закрытие  смены
          $this->add(new Form('zform'))->setVisible(false);
          $this->zform->add(new TextInput('zformqnt'));
          $this->zform->add(new TextInput('zformnal'));
          $this->zform->add(new TextInput('zformbnal'));
          $this->zform->add(new TextInput('zformcredit'));
          $this->zform->add(new TextInput('zformprepaid'));
          $this->zform->add(new TextInput('zformtotal'));
          $this->zform->add(new Button('cancelzform'))->onClick($this, 'cancelzformOnClick');
          $this->zform->add(new SubmitButton('savezform'))->onClick($this, 'savezformOnClick');

         */
    }


    public function onCheckList($sender) {
        $this->docpanel->setVisible(false);
        $this->docpanel->form2->setVisible(false);
        $this->docpanel->form3->setVisible(false);
        $this->docpanel->formcheck->setVisible(false);
        $this->docpanel->editserdetail->setVisible(false);
        $this->docpanel->wselitem->setVisible(false);
        $this->docpanel->catpan->setVisible(false);
        $this->docpanel->prodpan->setVisible(false);
        $this->docpanel->editdetail->setVisible(false);

        $this->checklistpan->setVisible(true);
        $this->checklistpan->statuspan->setVisible(true);
        $this->updatechecklist(null);
    }


    public function cancel2docOnClick($sender) {

        $this->docpanel->form2->setVisible(true);
        $this->docpanel->form3->setVisible(false);
    }

    public function cancel3docOnClick($sender) {

        $this->docpanel->form3->setVisible(true);
        $this->docpanel->formcheck->setVisible(false);
    }

    public function next1docOnClick($sender) {
        $this->pos = \App\Entity\Pos::load($this->form1->pos->getValue());

        $this->_store_id = $this->form1->store->getValue();
        $this->_salesource = $this->form1->salesource->getValue();
        $this->_pt = $this->form1->pricetype->getValue();

        if ($this->pos == null) {
            $this->setError("noselterm");
            return;
        }

        if ($this->_store_id == 0) {
            $this->setError("noselstore");
            return;
        }

        if (strlen($this->_pt) == 0) {
            $this->setError("noselpricetype");
            return;
        }
        $filter = \App\Filter::getFilter("armpos");

        $filter->pos = $this->form1->pos->getValue();
        $filter->store = $this->_store_id;
        $filter->pricetype = $this->_pt;
        $filter->salesource = $this->_salesource;

        $this->form1->setVisible(false);
        $this->docpanel->form2->setVisible(true);

        $this->newdoc(null);
    }

    public function newdoc($sender) {
        $this->docpanel->setVisible(true);

        $this->docpanel->form2->setVisible(true);


        $this->checklistpan->setVisible(false);
        $this->checklistpan->searchform->clean();

        $this->_doc = \App\Entity\Doc\Document::create('POSCheck');

        $this->_itemlist = array();
        $this->_serlist = array();
        $this->docpanel->form2->detail->Reload();
        $this->docpanel->form2->detailser->Reload();
        $this->calcTotal();

        $this->docpanel->form3->document_date->setDate(time());
        $this->docpanel->form3->document_number->setText($this->_doc->nextNumber());
        $this->docpanel->form3->customer->setKey(0);
        $this->docpanel->form3->customer->setText('');
        $this->docpanel->form3->paydisc->setText('0');
        $this->docpanel->form3->payamount->setText('0');
        $this->docpanel->form3->payed->setText('0');
        $this->docpanel->form3->exchange->setText('0');
        $this->docpanel->form3->discount->setText('');

        $this->docpanel->form2->setVisible(true);
        $this->docpanel->formcheck->setVisible(false);
    }

    public function topayOnClick($sender) {
        if (count($this->_itemlist) == 0 && count($this->_serlist) == 0) {
            $this->setError('noenterpos');
            return;
        }

        $this->form1->setVisible(false);
        $this->docpanel->form2->setVisible(false);
        $this->docpanel->form3->setVisible(true);
    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('tovar', $item->itemname));

        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('msr', $item->msr));
        $row->add(new Label('snumber', $item->snumber));
        $row->add(new Label('sdate', $item->sdate > 0 ? \App\Helper::fd($item->sdate) : ''));

        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));
        $row->add(new ClickLink('plus', $this, 'plusOnClick'));
        $row->add(new ClickLink('minus', $this, 'minusOnClick'))->setVisible($item->quantity > 1);

        $row->add(new Label('amount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
    }

    public function serOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('service', $item->service_name));

        $row->add(new Label('serquantity', H::fqty($item->quantity)));
        $row->add(new Label('serprice', H::fa($item->price)));

        $row->add(new Label('seramount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('serdelete'))->onClick($this, 'serdeleteOnClick');
        $row->add(new ClickLink('seredit'))->onClick($this, 'sereditOnClick');
    }

    public function addcodeOnClick($sender) {
        $code = trim($this->docpanel->form2->barcode->getText());
        $code = ltrim($code,'0');
        $store = $this->form1->store->getValue();
        $this->docpanel->form2->barcode->setText('');
        if ($code == '') {
            return;
        }


        $code_ = Item::qstr($code);
        $item = Item::getFirst(" item_id in(select item_id from store_stock where store_id={$store}) and  (item_code = {$code_} or bar_code = {$code_})");

        if ($item == null) {
            $this->setError("noitemcode", $code);
            return;
        }

        $qty = $item->getQuantity($store);
        if ($qty <= 0) {
            $this->setError("noitemonstore", $item->itemname);
        }

        foreach ($this->_itemlist as $ri => $_item) {
            if ($_item->bar_code == $code || $_item->item_code == $code) {
                $this->_itemlist[$ri]->quantity += 1;
                $this->docpanel->form2->detail->Reload();
                $this->calcTotal();

                return;
            }
        }


        $price = $item->getPrice($this->getPriveType(), $store);
        $item->price = $price;
        $item->quantity = 1;

        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

            $serial = '';
            $slist = $item->getSerials($store);
            if (count($slist) == 1) {
                $serial = array_pop($slist);
            }

            if (strlen($serial) == 0) {
                $this->setWarn('needs_serial');
                $this->docpanel->editdetail->setVisible(true);
                $this->docpanel->form2->setVisible(false);

                $this->docpanel->editdetail->edittovar->setKey($item->item_id);
                $this->docpanel->editdetail->edittovar->setText($item->itemname);
                $this->docpanel->editdetail->editserial->setText('');
                $this->docpanel->editdetail->editquantity->setText('1');
                $this->docpanel->editdetail->editprice->setText($item->price);

                return;
            } else {
                $item->snumber = $serial;
            }
        }
        $next = count($this->_itemlist) > 0 ? max(array_keys($this->_itemlist)) : 0;
        $item->rowid = $next + 1;


        $this->_itemlist[$item->rowid] = $item;

        $this->docpanel->form2->detail->Reload();
        $this->calcTotal();
    }

    public function editOnClick($sender) {
        $tovar = $sender->owner->getDataItem();
        $this->docpanel->editdetail->setVisible(true);
        $this->docpanel->editdetail->edittovar->setKey($tovar->item_id);
        $this->docpanel->editdetail->edittovar->setText($tovar->itemname);
        $this->docpanel->editdetail->editquantity->setText($tovar->quantity);
        $this->docpanel->editdetail->editprice->setText($tovar->price);
        $this->docpanel->editdetail->editserial->setText($tovar->snumber);

        $store = $this->form1->store->getValue();
        $qty = $tovar->getQuantity($store);

        $this->docpanel->editdetail->qtystock->setText(H::fqty($qty));

        $this->docpanel->form2->setVisible(false);
        $this->_rowid = $tovar->rowid;
    }

    public function plusOnClick($sender) {
        $tovar = $sender->owner->getDataItem();
        $tovar->quantity++;
        $this->docpanel->form2->detail->Reload();
        $this->calcTotal();
    }

    public function minusOnClick($sender) {
        $tovar = $sender->owner->getDataItem();
        if ($tovar->quantity > 1) {
            $tovar->quantity--;
        }
        $this->docpanel->form2->detail->Reload();
        $this->calcTotal();
    }

    public function deleteOnClick($sender) {

        $tovar = $sender->owner->getDataItem();

        $this->_itemlist = array_diff_key($this->_itemlist, array($tovar->rowid => $this->_itemlist[$tovar->rowid]));
        $this->docpanel->form2->detail->Reload();
        $this->calcTotal();
    }

    public function sereditOnClick($sender) {
        $ser = $sender->owner->getDataItem();
        $this->docpanel->editserdetail->setVisible(true);
        $this->docpanel->editserdetail->editser->setKey($ser->service_id);
        $this->docpanel->editserdetail->editser->setText($ser->service_name);
        $this->docpanel->editserdetail->editserquantity->setText($ser->quantity);
        $this->docpanel->editserdetail->editserprice->setText($ser->price);

        $this->docpanel->form2->setVisible(false);
        $this->_rowid = $ser->rowid;
    }

    public function serdeleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }

        $ser = $sender->owner->getDataItem();

        $this->_serlist = array_diff_key($this->_serlist, array($ser->rowid => $this->_serlist[$ser->rowid]));
        $this->docpanel->form2->detailser->Reload();
        $this->calcTotal();
    }

    public function addrowOnClick($sender) {
        $this->docpanel->editdetail->setVisible(true);
        $this->docpanel->editdetail->editquantity->setText("1");
        $this->docpanel->editdetail->editprice->setText("0");
        $this->docpanel->editdetail->qtystock->setText("");
        $this->docpanel->form2->setVisible(false);
        $this->_rowid = 0;
        $this->docpanel->tochecklist->setVisible(false);        
    }

    public function addserOnClick($sender) {
        $this->docpanel->editserdetail->setVisible(true);
        $this->docpanel->editserdetail->editserquantity->setText("1");
        $this->docpanel->editserdetail->editserprice->setText("0");

        $this->docpanel->form2->setVisible(false);
        $this->_rowid = 0;
    }

    public function saverowOnClick($sender) {
        $store = $this->form1->store->getValue();

        $id = $this->docpanel->editdetail->edittovar->getKey();
        if ($id == 0) {
            $this->setError("noselitem");
            return;
        }
        $item = Item::load($id);

        $item->quantity = $this->docpanel->editdetail->editquantity->getText();
        $item->snumber = $this->docpanel->editdetail->editserial->getText();

        $qstock = $item->getQuantity($store);

        $item->price = H::fa($this->docpanel->editdetail->editprice->getText());

        if ($item->quantity > $qstock) {
            $this->setWarn('inserted_extra_count');
        }

        if (strlen($item->snumber) == 0 && $item->useserial == 1 && $this->_tvars["usesnumber"] == true) {
            $this->setError("needs_serial");
            return;
        }

        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {
            $slist = $item->getSerials($store);

            if (in_array($item->snumber, $slist) == false) {
                $this->setError('invalid_serialno');
                return;
            }
        }

        if ($this->_rowid > 0) {
            $item->rowid = $this->_rowid;
            
                    
                $this->docpanel->editdetail->setVisible(false);
                $this->docpanel->form2->setVisible(true);

                $this->docpanel->editdetail->edittovar->setKey(0);
                $this->docpanel->editdetail->edittovar->setText('');

                $this->docpanel->editdetail->editquantity->setText("1");

                $this->docpanel->editdetail->editprice->setText("");
                $this->docpanel->editdetail->editserial->setText("");
                $this->docpanel->wselitem->setVisible(false);           
        $this->docpanel->catpan->setVisible(false);
        $this->docpanel->prodpan->setVisible(false);
            
            
        } else {
            $next = count($this->_itemlist) > 0 ? max(array_keys($this->_itemlist)) : 0;
            $item->rowid = $next + 1;
        }
        $this->_itemlist[$item->rowid] = $item;

        $this->_rowid = 0;


        $this->docpanel->form2->detail->Reload();
       
    

        $this->calcTotal();
        
       //очищаем  форму
        $this->docpanel->editdetail->edittovar->setKey(0);
        $this->docpanel->editdetail->edittovar->setText('');

        $this->docpanel->editdetail->editquantity->setText("1");

        $this->docpanel->editdetail->editprice->setText("");
        $this->docpanel->editdetail->qtystock->setText("");
        
        
    }

    public function saveserOnClick($sender) {

        $id = $this->docpanel->editserdetail->editser->getKey();
        if ($id == 0) {
            $this->setError("noselservice");
            return;
        }
        $ser = Service::load($id);

        $ser->quantity = $this->docpanel->editserdetail->editserquantity->getText();

        $ser->price = H::fa($this->docpanel->editserdetail->editserprice->getText());

        if ($this->_rowid > 0) {
            $ser->rowid = $this->_rowid;
        } else {
            $next = count($this->_serlist) > 0 ? max(array_keys($this->_serlist)) : 0;
            $ser->rowid = $next + 1;
        }
        $this->_serlist[$ser->rowid] = $ser;

        $this->_rowid = 0;

        $this->docpanel->editserdetail->setVisible(false);
        $this->docpanel->form2->setVisible(true);
        $this->docpanel->form2->detailser->Reload();

        //очищаем  форму
        $this->docpanel->editserdetail->editser->setKey(0);
        $this->docpanel->editserdetail->editser->setText('');
        $this->docpanel->editserdetail->editserquantity->setText("1");
        $this->docpanel->editserdetail->editserprice->setText("");
        $this->calcTotal();
    }

    public function cancelrowOnClick($sender) {
        $this->docpanel->editdetail->setVisible(false);
        $this->docpanel->editserdetail->setVisible(false);
        $this->docpanel->form2->setVisible(true);
        $this->docpanel->wselitem->setVisible(false);
        $this->docpanel->catpan->setVisible(false);
        $this->docpanel->prodpan->setVisible(false);
        
        $this->docpanel->form2->detail->Reload();        
        
        //очищаем  форму
        $this->docpanel->editdetail->edittovar->setKey(0);
        $this->docpanel->editdetail->edittovar->setText('');

        $this->docpanel->editdetail->editquantity->setText("1");

        $this->docpanel->editdetail->editprice->setText("");
        $this->docpanel->editdetail->qtystock->setText("");
        $this->docpanel->tochecklist->setVisible(true);
        
    }

    
    //справочник
    public function onOpenItemSel($sender) {
        $this->docpanel->wselitem->setVisible(true);
        $this->docpanel->catpan->setVisible(false);
        $this->docpanel->prodpan->setVisible(false);
        
        $this->docpanel->wselitem->setPriceType($this->getPriveType());
        $this->docpanel->wselitem->Reload();
    }

    public function onSelectItem($item_id, $itemname) {
        $this->docpanel->editdetail->edittovar->setKey($item_id);
        $this->docpanel->editdetail->edittovar->setText($itemname);
        $this->OnChangeItem($this->docpanel->editdetail->edittovar);
    }    
    //  панель категорий
   public function onOpenCatPan($sender) {
        $this->docpanel->wselitem->setVisible(false);
        $this->docpanel->catpan->setVisible(true);
        $this->docpanel->prodpan->setVisible(false);
        $this->_catlist = Category::find(" coalesce(parent_id,0)=0  ");
       
        $this->docpanel->catpan->catlist->Reload();
    }
     //категории
    public function onCatRow($row) {
        $cat = $row->getDataItem();
        $row->add(new ClickLink('catbtn'))->onClick($this, 'onCatBtnClick');
        $row->catbtn->add(new Label('catname', $cat->cat_name));
        $row->catbtn->add(new Image('catimage', "/loadimage.php?id=" . $cat->image_id));
    }

    //товары
    public function onProdRow($row) {
        //  $store_id = $this->setupform->store->getValue();

        $prod = $row->getDataItem();
        $prod->price = $prod->getPrice($this->getPriveType() );
        $row->add(new ClickLink('prodbtn'))->onClick($this, 'onProdBtnClick');
        $row->prodbtn->add(new Label('prodname', $prod->itemname));
        $row->prodbtn->add(new Label('prodprice', H::fa($prod->price)));
        $row->prodbtn->add(new Image('prodimage', "/loadimage.php?id=" . $prod->image_id));
    }

    //выбрана  группа
    public function onCatBtnClick($sender) {
        $cat = $sender->getOwner()->getDataItem();
        $catlist = Category::find("  detail  not  like '%<nofastfood>1</nofastfood>%' and   coalesce(parent_id,0)= " . $cat->cat_id);
        if (count($catlist) > 0) {
            $this->_catlist = $catlist;
            $this->docpanel->catpan->catlist->Reload();
        } else {
            $this->_prodlist = Item::find('disabled<>1  and  item_type in (1,4 )  and cat_id=' . $cat->cat_id);
            $this->docpanel->catpan->setVisible(false);
            $this->docpanel->prodpan->setVisible(true);
            $this->docpanel->prodpan->prodlist->Reload();
        }

    }

    // выбран  товар
    public function onProdBtnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        $this->docpanel->editdetail->edittovar->setKey($item->item_id);
        $this->docpanel->editdetail->edittovar->setText($item->itemname);
        $this->OnChangeItem($this->docpanel->editdetail->edittovar);

 
        $this->_catlist = Category::find(" coalesce(parent_id,0)=0  ");
        $this->docpanel->catpan->catlist->Reload();


        $this->docpanel->catpan->setVisible(true);
        $this->docpanel->prodpan->setVisible(false);

    }



    private function calcTotal() {

        $total = 0;

        foreach ($this->_itemlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;
        }
        foreach ($this->_serlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;
        }
        $this->docpanel->form2->total->setText(H::fa($total));
        $this->docpanel->form3->total2->setText(H::fa($total));
        $this->docpanel->form3->payamount->setText(H::fa($total));
        $this->docpanel->form3->payed->setText(H::fa($total));
    }

    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $item = Item::load($id);
        $store = $this->form1->store->getValue();

        $price = $item->getPrice($this->getPriveType(), $store);
        $qty = $item->getQuantity($store);

        $this->docpanel->editdetail->qtystock->setText(H::fqty($qty));
        $this->docpanel->editdetail->editprice->setText($price);
        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

            $serial = '';
            $slist = $item->getSerials($store);
            if (count($slist) == 1) {
                $serial = array_pop($slist);
            }
            $this->docpanel->editdetail->editserial->setText($serial);
        }


        $this->updateAjax(array('qtystock', 'editprice', 'editserial'));
    }

    public function OnAutoItem($sender) {

        $text = trim($sender->getText());
        return Item::findArrayAC($text);
    }

    public function OnAutoSer($sender) {

        $text = trim($sender->getText());
        $text = Service::qstr('%' . $text . '%');
        return Service::findArray('service_name', "disabled <> 1 and service_name like {$text}");
    }

    public function OnChangeSer($sender) {
        $id = $sender->getKey();
        $ser = Service::load($id);
        $this->docpanel->editserdetail->editserprice->setText($ser->price);

        $this->updateAjax(array('editserprice'));
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    public function OnChangeCustomer($sender) {
        $this->docpanel->form3->discount->setVisible(false);
        $total = $this->docpanel->form3->total2->getText();
        $disc = 0;

        $customer_id = $this->docpanel->form3->customer->getKey();

        if ($customer_id > 0) {
            $cust = Customer::load($customer_id);

            $disctext = "";
            if (doubleval($cust->discount) > 0) {
                $disctext = H::l("custdisc") . " {$cust->discount}%";
                $disc = round($total * ($customer->discount / 100));
            } else {
                $bonus = $cust->getBonus();
                if ($bonus > 0) {
                    $disctext = H::l("custbonus") . " {$bonus} ";

                    $total = $this->docpanel->form2->total->getText();


                    if ($total >= $bonus) {
                        $disc = $bonus;
                    } else {
                        $disc = $total;
                    }
                }
            }
            $this->docpanel->form3->discount->setText($disctext);
            $this->docpanel->form3->discount->setVisible(true);

        }
        $this->docpanel->form3->paydisc->setText(H::fa($disc));
        $this->docpanel->form3->payamount->setText(H::fa($total - $disc));
    }

    //добавление нового контрагента
    public function addcustOnClick($sender) {
        $this->editcust->setVisible(true);
        $this->docpanel->form3->setVisible(false);

        $this->editcust->editcustname->setText('');
        $this->editcust->editphone->setText('');
    }

    public function savecustOnClick($sender) {
        $custname = trim($this->editcust->editcustname->getText());
        if (strlen($custname) == 0) {
            $this->setError("entername");
            return;
        }
        $cust = new Customer();
        $cust->customer_name = $custname;
        $cust->phone = $this->editcust->editphone->getText();
        $cust->phone = \App\Util::handlePhone($cust->phone);

        if (strlen($cust->phone) > 0 && strlen($cust->phone) != H::PhoneL()) {
            $this->setError("tel10", H::PhoneL());
            return;
        }

        $c = Customer::getByPhone($cust->phone);
        if ($c != null) {
            if ($c->customer_id != $cust->customer_id) {
                $this->setError("existcustphone");
                return;
            }
        }

        $cust->type = 1;
        $cust->save();
        $this->docpanel->form3->customer->setText($cust->customer_name);
        $this->docpanel->form3->customer->setKey($cust->customer_id);

        $this->editcust->setVisible(false);
        $this->docpanel->form3->setVisible(true);
        $this->docpanel->form3->discount->setVisible(false);
        $this->_discount = 0;
    }

    public function cancelcustOnClick($sender) {
        $this->editcust->setVisible(false);
        $this->docpanel->form3->setVisible(true);
    }

    public function savedocOnClick($sender) {

        $this->_doc->document_number = $this->docpanel->form3->document_number->getText();

        $doc = Document::getFirst("   document_number = '{$this->_doc->document_number}' ");
        if ($doc instanceof Document) {   //если уже  кто то  сохранил  с таким номером
            $this->_doc->document_number = $this->_doc->nextNumber();
            $this->docpanel->form3->document_number->setText($this->_doc->document_number);
        }
        if (false == $this->_doc->checkUniqueNumber()) {
            $next = $this->_doc->nextNumber();
            $this->docpanel->form3->document_number->setText($next);
            $this->_doc->document_number = $next;
            if (strlen($next) == 0) {
                $this->setError('docnumbercancreated');
            }
        }
        $this->_doc->document_date = $this->docpanel->form3->document_date->getDate();
        $this->_doc->notes = $this->docpanel->form3->notes->getText();

        $this->_doc->customer_id = $this->docpanel->form3->customer->getKey();
        $this->_doc->payamount = $this->docpanel->form3->payamount->getText();

        $this->_doc->headerdata['time'] = time();
        $this->_doc->payed = $this->docpanel->form3->payed->getText();
        $this->_doc->headerdata['payed'] = $this->docpanel->form3->payed->getText();
        $this->_doc->headerdata['exchange'] = $this->docpanel->form3->exchange->getText();
        $this->_doc->headerdata['paydisc'] = $this->docpanel->form3->paydisc->getText();
        $this->_doc->headerdata['payment'] = $this->docpanel->form3->payment->getValue();

        if ($this->_doc->amount > 0 && $this->_doc->payamount > $this->_doc->payed && $this->_doc->customer_id == 0) {
            $this->setError("mustsel_cust");
            return;
        }
        if ($this->docpanel->form3->payment->getValue() == 0 && $this->_doc->payed > 0) {
            $this->setError("noselmf");
            return;
        }

        $this->_doc->headerdata['pos'] = $this->pos->pos_id;
        $this->_doc->headerdata['pos_name'] = $this->pos->pos_name;
        $this->_doc->headerdata['store'] = $this->_store_id;
        $this->_doc->headerdata['salesource'] = $this->_salesource;
        $this->_doc->headerdata['pricetype'] = $this->getPriveType();

        $this->_doc->firm_id = $this->pos->firm_id;

        $firm = H::getFirmData($this->_doc->firm_id);
        $this->_doc->headerdata["firm_name"] = $firm['firm_name'];
        $this->_doc->headerdata["inn"] = $firm['inn'];
        $this->_doc->headerdata["address"] = $firm['address'];
        $this->_doc->headerdata["phone"] = $firm['phone'];

        $this->_doc->packDetails('detaildata', $this->_itemlist);
        $this->_doc->packDetails('services', $this->_serlist);

        $this->_doc->amount = $this->docpanel->form3->total2->getText();
        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {

            // проверка на минус  в  количестве
            $allowminus = System::getOption("common", "allowminus");
            if ($allowminus != 1) {

                foreach ($this->_itemlist as $item) {
                    $qty = $item->getQuantity($this->_doc->headerdata['store']);
                    if ($qty < $item->quantity) {
                        $this->setError("nominus", H::fqty($qty), $item->itemname);
                        return;
                    }
                }
            }

            if ($this->pos->usefisc == 1 && $this->_tvars['ppo'] == true) {


                $ret = \App\Modules\PPO\PPOHelper::check($this->_doc);
                if ($ret['success'] == false && $ret['docnumber'] > 0) {
                    //повторяем для  нового номера
                    $this->pos->fiscdocnumber = $ret['docnumber'];
                    $this->pos->save();
                    $ret = \App\Modules\PPO\PPOHelper::check($this->_doc);
                }
                if ($ret['success'] == false) {
                    $this->setError($ret['data']);
                     $conn->RollbackTrans();
                    return;
                } else {
                    //  $this->setSuccess("Выполнено") ;
                    if ($ret['docnumber'] > 0) {
                        $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                        $this->pos->save();
                        $this->_doc->headerdata["fiscalnumber"] = $ret['docnumber'];
                    } else {
                        $this->setError("ppo_noretnumber");
                         $conn->RollbackTrans();
                        return;
                    }
                }
            }


            $this->_doc->save();
            $this->_doc->updateStatus(Document::STATE_NEW);

            $this->_doc->updateStatus(Document::STATE_EXECUTED);
            $conn->CommitTrans();
        } catch(\Throwable $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());

            $logger->error($ee->getMessage() . " Документ " . $this->_doc->meta_desc);
            return;
        }
        $this->docpanel->form3->customer->setKey(0);
        $this->docpanel->form3->customer->setText('');
        $this->docpanel->form3->payment->setValue(H::getDefMF());
        $this->docpanel->form3->setVisible(false);
        $this->docpanel->formcheck->setVisible(true);
        $this->docpanel->form3->notes->setText('');
        $check = $this->_doc->generatePosReport();
        $this->docpanel->formcheck->showcheck->setText($check, true);
    }


    public function OnOpenShift() {
        $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, true);
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, true);
        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {
            $this->setSuccess("ppo_shiftopened");
            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
                $this->_doc->headerdata["fiscalnumber"] = $ret['docnumber'];
            } else {
                $this->setError("ppo_noretnumber");
                return;
            }
            \App\Modules\PPO\PPOHelper::clearStat($this->pos->pos_id);
        }


        $this->pos->save();
        return true;
    }

    public function OnCloseShift($sender) {
        $ret = $this->zform();
        if ($ret == true) {
            $this->closeshift();
        }
    }

    public function zform() {

        $stat = \App\Modules\PPO\PPOHelper::getStat($this->pos->pos_id);
        $rstat = \App\Modules\PPO\PPOHelper::getStat($this->pos->pos_id, true);

        $ret = \App\Modules\PPO\PPOHelper::zform($this->pos->pos_id, $stat, $rstat);
        if (strpos($ret['data'], 'ZRepAlreadyRegistered')) {
            return true;
        }
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::zform($this->pos->pos_id, $stat, $rstat);
        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {

            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
            } else {
                $this->setError("ppo_noretnumber");
                return;
            }
        }


        return true;
    }

    public function closeshift() {
        $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, false);
        if ($ret['success'] == false && $ret['docnumber'] > 0) {
            //повторяем для  нового номера
            $this->pos->fiscdocnumber = $ret['docnumber'];
            $this->pos->save();
            $ret = \App\Modules\PPO\PPOHelper::shift($this->pos->pos_id, false);
        }
        if ($ret['success'] == false) {
            $this->setError($ret['data']);
            return false;
        } else {
            $this->setSuccess("ppo_shiftclosed");
            if ($ret['docnumber'] > 0) {
                $this->pos->fiscdocnumber = $ret['doclocnumber'] + 1;
                $this->pos->save();
            } else {
                $this->setError("ppo_noretnumber");
                return;
            }
            \App\Modules\PPO\PPOHelper::clearStat($this->pos->pos_id);
        }


        return true;
    }


    public function onDocRow($row) {
        $doc = $row->getDataItem();
        $row->add(new ClickLink('rownumber', $this, 'OnDocViewClick'))->setValue($doc->document_number);

        $row->add(new Label('rowamount', H::fa($doc->amount)));
        $row->add(new Label('rowdate', H::fd($doc->document_date)));
        $row->add(new Label('rownotes', $doc->notes));

        if ($doc->document_id == $this->_doc->document_id) {
            $row->setAttribute('class', 'table-success');
        }

    }

    public function updatechecklist($sender) {
        $where = "meta_name='PosCheck' ";
        if ($sender instanceof Form) {
            $text = trim($sender->searchnumber->getText());
            $cust = $sender->searchcust->getKey();
            if (strlen($text) > 0) {
                $where .= " and document_number=" . Document::qstr($text);
            }
            if (strlen($text) == 0 && $cust > 0) {
                $where .= " and customer_id=" . $cust;
            }


        }
        $this->_doclist = Document::find($where, 'document_id desc');
        $this->checklistpan->checklist->Reload();
    }

    public function OnDocViewClick($sender) {
        $this->_doc = $sender->getOwner()->getDataItem();
        $this->OnDocView();

    }

    public function OnDocView() {
        $this->checklistpan->statuspan->setVisible(true);

        $this->checklistpan->statuspan->docview->setDoc($this->_doc);
        $this->checklistpan->checklist->Reload(false);
        //      $this->updateStatusButtons();
        $this->goAnkor('dankor');
    }

    //тип  цены с  учетом  контрагента
    private function getPriveType() {
        $id = $this->docpanel->form3->customer->getKey();
        if ($id > 0) {
            $cust = \App\Entity\Customer::load($id);
            if (strlen($cust->pricetype) > 4) {
                return $cust->pricetype;
            }


        }

        return $this->_pt;
    }

}
