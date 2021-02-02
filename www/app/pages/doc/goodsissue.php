<?php

namespace App\Pages\Doc;

use App\Application as App;
use App\Entity\Customer;
use App\Entity\Doc\Document;
use App\Entity\Item;
use App\Entity\MoneyFund;
use App\Entity\Stock;
use App\Entity\Store;
use App\Helper as H;
use App\System;
use Zippy\Html\DataList\DataView;
use Zippy\Html\Form\AutocompleteTextInput;
use Zippy\Html\Form\Button;
use Zippy\Html\Form\Date;
use Zippy\Html\Form\DropDownChoice;
use Zippy\Html\Form\Form;
use Zippy\Html\Form\SubmitButton;
use Zippy\Html\Form\TextInput;
use Zippy\Html\Label;
use Zippy\Html\Link\ClickLink;
use Zippy\Html\Link\SubmitLink;

/**
 * Страница  ввода  расходной накладной
 */
class GoodsIssue extends \App\Pages\Base
{

    public  $_itemlist  = array();
    private $_doc;
    private $_basedocid = 0;
    private $_rowid     = 0;
    private $_orderid   = 0;
    private $_prevcust  = 0;   // преыдущий контрагент

    public function __construct($docid = 0, $basedocid = 0) {
        parent::__construct();

        $common = System::getOptions("common");

        $this->_tvars["colspan"] = $common['usesnumber'] == 1 ? 8 : 6;


        $this->add(new Form('docform'));
        $this->docform->add(new TextInput('document_number'));

        $this->docform->add(new Date('document_date'))->setDate(time());

        $this->docform->add(new DropDownChoice('payment', MoneyFund::getList(true, true), H::getDefMF()))->onChange($this, 'OnPayment');

        $this->docform->add(new Label('discount'))->setVisible(false);
        $this->docform->add(new TextInput('editpaydisc'));
        $this->docform->add(new SubmitButton('bpaydisc'))->onClick($this, 'onPayDisc');
        $this->docform->add(new Label('paydisc', 0));


        $this->docform->add(new TextInput('editpayamount'));
        $this->docform->add(new SubmitButton('bpayamount'))->onClick($this, 'onPayAmount');
        $this->docform->add(new TextInput('editpayed', "0"));
        $this->docform->add(new SubmitButton('bpayed'))->onClick($this, 'onPayed');
        $this->docform->add(new Label('payed', 0));
        $this->docform->add(new Label('payamount', 0));


        $this->docform->add(new TextInput('barcode'));
        $this->docform->add(new SubmitLink('addcode'))->onClick($this, 'addcodeOnClick');


        $this->docform->add(new DropDownChoice('store', Store::getList(), H::getDefStore()))->onChange($this, 'OnChangeStore');

        $this->docform->add(new SubmitLink('addcust'))->onClick($this, 'addcustOnClick');

        $this->docform->add(new AutocompleteTextInput('customer'))->onText($this, 'OnAutoCustomer');
        $this->docform->customer->onChange($this, 'OnChangeCustomer');

        $this->docform->add(new DropDownChoice('firm', \App\Entity\Firm::getList(), H::getDefFirm()))->onChange($this, 'OnCustomerFirm');
        $this->docform->add(new DropDownChoice('contract', array(), 0))->setVisible(false);;


        $this->docform->add(new DropDownChoice('pricetype', Item::getPriceTypeList(), H::getDefPriceType()));


        $this->docform->add(new TextInput('order'));

        $this->docform->add(new TextInput('notes'));

        $cp = \App\Session::getSession()->clipboard;
        $this->docform->add(new ClickLink('paste', $this, 'onPaste'))->setVisible(is_array($cp) && count($cp) > 0);

        $this->docform->add(new SubmitLink('addrow'))->onClick($this, 'addrowOnClick');
        $this->docform->add(new SubmitButton('savedoc'))->onClick($this, 'savedocOnClick');
        $this->docform->add(new SubmitButton('execdoc'))->onClick($this, 'savedocOnClick');


        $this->docform->add(new Button('backtolist'))->onClick($this, 'backtolistOnClick');

        $this->docform->add(new Label('total'));


        $this->add(new Form('editdetail'))->setVisible(false);
        $this->editdetail->add(new TextInput('editquantity'))->setText("1");
        $this->editdetail->add(new TextInput('editprice'));
        $this->editdetail->add(new TextInput('editserial'));

        $this->editdetail->add(new AutocompleteTextInput('edittovar'))->onText($this, 'OnAutoItem');
        $this->editdetail->edittovar->onChange($this, 'OnChangeItem', true);


        $this->editdetail->add(new ClickLink('openitemsel', $this, 'onOpenItemSel'));
        $this->editdetail->add(new Label('qtystock'));

        $this->editdetail->add(new Button('cancelrow'))->onClick($this, 'cancelrowOnClick');
        $this->editdetail->add(new SubmitButton('submitrow'))->onClick($this, 'saverowOnClick');

        $this->add(new \App\Widgets\ItemSel('wselitem', $this, 'onSelectItem'))->setVisible(false);

        //добавление нового контрагента
        $this->add(new Form('editcust'))->setVisible(false);
        $this->editcust->add(new TextInput('editcustname'));
        $this->editcust->add(new TextInput('editphone'));
        $this->editcust->add(new TextInput('editaddress'));
        $this->editcust->add(new Button('cancelcust'))->onClick($this, 'cancelcustOnClick');
        $this->editcust->add(new SubmitButton('savecust'))->onClick($this, 'savecustOnClick');

        if ($docid > 0) {    //загружаем   содержимое  документа настраницу
            $this->_doc = Document::load($docid)->cast();
            $this->docform->document_number->setText($this->_doc->document_number);

            $this->docform->pricetype->setValue($this->_doc->headerdata['pricetype']);
            $this->docform->total->setText($this->_doc->amount);

            $this->docform->document_date->setDate($this->_doc->document_date);
            $this->docform->payment->setValue($this->_doc->headerdata['payment']);

            $this->docform->payamount->setText($this->_doc->payamount);
            $this->docform->editpayamount->setText($this->_doc->payamount);
            $this->docform->paydisc->setText($this->_doc->headerdata['paydisc']);
            $this->docform->editpaydisc->setText($this->_doc->headerdata['paydisc']);
            $this->docform->payed->setText($this->_doc->payed);
            $this->docform->editpayed->setText($this->_doc->payed);

            $this->OnPayment($this->docform->payment);


            $this->docform->store->setValue($this->_doc->headerdata['store']);
            $this->docform->customer->setKey($this->_doc->customer_id);

            if ($this->_doc->customer_id) {
                $this->docform->customer->setText($this->_doc->customer_name);
            } else {
                $this->docform->customer->setText($this->_doc->headerdata['customer_name']);
            }

            $this->docform->notes->setText($this->_doc->notes);
            $this->docform->order->setText($this->_doc->headerdata['order']);
            $this->_orderid = $this->_doc->headerdata['order_id'];
            $this->_prevcust = $this->_doc->customer_id;

            $this->docform->firm->setValue($this->_doc->firm_id);
            $this->OnChangeCustomer($this->docform->customer);

            $this->docform->contract->setValue($this->_doc->headerdata['contract_id']);


            $this->_itemlist = $this->_doc->unpackDetails('detaildata');


        } else {
            $this->_doc = Document::create('GoodsIssue');
            $this->docform->document_number->setText($this->_doc->nextNumber());

            if ($basedocid > 0) {  //создание на  основании
                $basedoc = Document::load($basedocid);
                if ($basedoc instanceof Document) {
                    $this->_basedocid = $basedocid;
                    if ($basedoc->meta_name == 'Order') {

                        $this->docform->customer->setKey($basedoc->customer_id);
                        $this->docform->customer->setText($basedoc->customer_name);

                        $this->docform->pricetype->setValue($basedoc->headerdata['pricetype']);
                        $this->docform->store->setValue($basedoc->headerdata['store']);
                        $this->_orderid = $basedocid;
                        $this->docform->order->setText($basedoc->document_number);


                        $notfound = array();
                        $order = $basedoc->cast();


                        //проверяем  что уже есть отправка
                        $list = $order->getChildren('GoodsIssue');

                        if (count($list) > 0) {

                            $this->setWarn('order_has_sent');
                        }
                        $this->docform->total->setText($order->amount);

                        $this->OnChangeCustomer($this->docform->customer);


                        $this->_itemlist = $basedoc->unpackDetails('detaildata');
                        $this->calcTotal();
                        $this->calcPay();

                    }
                    if ($basedoc->meta_name == 'Invoice') {

                        $this->docform->customer->setKey($basedoc->customer_id);
                        $this->docform->customer->setText($basedoc->customer_name);

                        $this->docform->pricetype->setValue($basedoc->headerdata['pricetype']);
                        $this->docform->store->setValue($basedoc->headerdata['store']);

                        $notfound = array();
                        $invoice = $basedoc->cast();

                        $this->docform->total->setText($invoice->amount);
                        $this->docform->firm->setValue($basedoc->firm_id);

                        $this->OnChangeCustomer($this->docform->customer);
                        $this->docform->contract->setValue($basedoc->headerdata['contract_id']);


                        $this->_itemlist = $basedoc->unpackDetails('detaildata');
                        $this->calcTotal();
                        $this->calcPay();

                        if ($invoice->payamount > 0) {
                            $this->docform->payment->setValue(MoneyFund::PREPAID);// предоплата
                            $this->OnPayment($this->docform->payment);

                        }


                    }


                    if ($basedoc->meta_name == 'GoodsIssue') {

                        $this->docform->customer->setKey($basedoc->customer_id);
                        if ($basedoc->customer_id > 0) {
                            $this->docform->customer->setText($basedoc->customer_name);
                        } else {
                            $this->docform->customer->setText($basedoc->headerdata['customer_name']);
                        }


                        $this->docform->pricetype->setValue($basedoc->headerdata['pricetype']);
                        $this->docform->store->setValue($basedoc->headerdata['store']);


                        $this->docform->firm->setValue($basedoc->firm_id);

                        $this->OnChangeCustomer($this->docform->customer);
                        $this->docform->contract->setValue($basedoc->headerdata['contract_id']);


                        foreach ($basedoc->unpackDetails('detaildata') as $item) {
                            $item->price = $item->getPrice($basedoc->headerdata['pricetype']);
                            $this->_itemlist[$item->item_id] = $item;
                        }
                        $this->calcTotal();
                        $this->calcPay();
                    }
                    if ($basedoc->meta_name == 'ServiceAct') {

                        $this->docform->notes->setText(H::l('basedon') . $basedoc->document_number);
                        $this->docform->customer->setKey($basedoc->customer_id);
                        $this->docform->customer->setText($basedoc->customer_name);
                    }

                }
            }
        }

        $this->docform->add(new DataView('detail', new \Zippy\Html\DataList\ArrayDataSource(new \Zippy\Binding\PropertyBinding($this, '_itemlist')), $this, 'detailOnRow'))->Reload();
        if (false == \App\ACL::checkShowDoc($this->_doc)) {
            return;
        }

        $this->_tvars['manlist'] = array();

        foreach (Item::getManufacturers() as $man) {
            $this->_tvars['manlist'][] = array('mitem' => $man);
        }


    }

    public function detailOnRow($row) {
        $item = $row->getDataItem();

        $row->add(new Label('num', $row->getAllNumber()));
        $row->add(new Label('tovar', $item->itemname));

        $row->add(new Label('code', $item->item_code));
        $row->add(new Label('msr', $item->msr));
        $row->add(new Label('snumber', $item->snumber));
        $row->add(new Label('sdate', $item->sdate > 0 ? date('Y-m-d', $item->sdate) : ''));

        $row->add(new Label('quantity', H::fqty($item->quantity)));
        $row->add(new Label('price', H::fa($item->price)));

        $row->add(new Label('amount', H::fa($item->quantity * $item->price)));
        $row->add(new ClickLink('delete'))->onClick($this, 'deleteOnClick');
        $row->add(new ClickLink('edit'))->onClick($this, 'editOnClick');
    }

    public function deleteOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }

        $tovar = $sender->owner->getDataItem();
        // unset($this->_itemlist[$tovar->tovar_id]);

        $this->_itemlist = array_diff_key($this->_itemlist, array($tovar->item_id => $this->_itemlist[$tovar->item_id]));
        $this->docform->detail->Reload();
        $this->calcTotal();
        $this->calcPay();
    }

    public function addrowOnClick($sender) {
        $this->editdetail->setVisible(true);
        $this->editdetail->editquantity->setText("1");
        $this->editdetail->editprice->setText("0");
        $this->editdetail->qtystock->setText("");
        $this->docform->setVisible(false);
        $this->_rowid = 0;
    }

    public function editOnClick($sender) {
        $item = $sender->getOwner()->getDataItem();
        $this->editdetail->setVisible(true);
        $this->docform->setVisible(false);


        $this->editdetail->edittovar->setKey($item->item_id);
        $this->editdetail->edittovar->setText($item->itemname);

        $this->OnChangeItem($this->editdetail->edittovar);

        $this->editdetail->editprice->setText($item->price);
        $this->editdetail->editquantity->setText($item->quantity);
        $this->editdetail->editserial->setText($item->serial);

        $this->_rowid = $item->item_id;
    }

    public function saverowOnClick($sender) {

        $id = $this->editdetail->edittovar->getKey();
        if ($id == 0) {
            $this->setError("noselitem");
            return;
        }
        $item = Item::load($id);
        $store_id = $this->docform->store->getValue();

        $item->quantity = $this->editdetail->editquantity->getText();
        $item->snumber = $this->editdetail->editserial->getText();
        $qstock = $this->editdetail->qtystock->getText();

        $item->price = $this->editdetail->editprice->getText();

        if ($item->quantity > $qstock) {

            $this->setWarn('inserted_extra_count');
        }

        if (strlen($item->snumber) == 0 && $item->useserial == 1 && $this->_tvars["usesnumber"] == true) {

            $this->setError("needs_serial");
            return;
        }

        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {
            $slist = $item->getSerials($store_id);

            if (in_array($item->snumber, $slist) == false) {

                $this->setWarn('invalid_serialno');
            } else {
                $st = Stock::getFirst("store_id={$store_id} and item_id={$item->item_id} and snumber=" . Stock::qstr($item->snumber));
                if ($st instanceof Stock) {
                    $item->sdate = $st->sdate;
                }

            }


        }

        $tarr = array();

        foreach ($this->_itemlist as $k => $value) {

            if ($this->_rowid > 0 && $this->_rowid == $k) {
                $tarr[$item->item_id] = $item;    // заменяем
            } else {
                $tarr[$k] = $value;    // старый
            }

        }

        if ($this->_rowid == 0) {        // в конец
            $tarr[$item->item_id] = $item;
        }
        $this->_itemlist = $tarr;
        $this->_rowid = 0;

        $this->editdetail->setVisible(false);
        $this->wselitem->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->detail->Reload();

        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
        $this->editdetail->editserial->setText("");
        $this->calcTotal();
        $this->calcPay();
    }

    public function cancelrowOnClick($sender) {
        $this->wselitem->setVisible(false);
        $this->editdetail->setVisible(false);
        $this->docform->setVisible(true);
        //очищаем  форму
        $this->editdetail->edittovar->setKey(0);
        $this->editdetail->edittovar->setText('');

        $this->editdetail->editquantity->setText("1");

        $this->editdetail->editprice->setText("");
    }

    public function savedocOnClick($sender) {
        if (false == \App\ACL::checkEditDoc($this->_doc)) {
            return;
        }


        $this->_doc->document_number = $this->docform->document_number->getText();
        $this->_doc->document_date = $this->docform->document_date->getDate();
        $this->_doc->notes = $this->docform->notes->getText();
        //   $this->_doc->order = $this->docform->order->getText();
        $this->_doc->headerdata['customer_name'] = $this->docform->customer->getText();
        $this->_doc->customer_id = $this->docform->customer->getKey();
        if ($this->_doc->customer_id > 0) {
            $customer = Customer::load($this->_doc->customer_id);
            $this->_doc->headerdata['customer_name'] = $this->docform->customer->getText() . ' ' . $customer->phone;
        }
        $this->_doc->headerdata['contract_id'] = $this->docform->contract->getValue();
        $this->_doc->firm_id = $this->docform->firm->getValue();
        if ($this->_doc->firm_id > 0) {
            $this->_doc->headerdata['firm_name'] = $this->docform->firm->getValueName();
        }

        $this->_doc->payamount = $this->docform->payamount->getText();

        $this->_doc->payed = $this->docform->payed->getText();
        $this->_doc->headerdata['paydisc'] = $this->docform->paydisc->getText();

        $this->_doc->headerdata['payment'] = $this->docform->payment->getValue();

        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::PREPAID) {
            $this->_doc->headerdata['paydisc'] = 0;
            $this->_doc->payed = 0;
            $this->_doc->payamount = 0;
        }
        if ($this->_doc->headerdata['payment'] == \App\Entity\MoneyFund::CREDIT) {
            $this->_doc->payed = 0;
        }


        if ($this->checkForm() == false) {
            return;
        }

        $this->_doc->headerdata['order_id'] = $this->_orderid;
        $this->_doc->headerdata['order'] = $this->docform->order->getText();

        $this->_doc->headerdata['store'] = $this->docform->store->getValue();
        $this->_doc->headerdata['store_name'] = $this->docform->store->getValueName();
        $this->_doc->headerdata['pricetype'] = $this->docform->pricetype->getValue();
        $this->_doc->headerdata['pricetypename'] = $this->docform->pricetype->getValueName();
        $this->_doc->headerdata['order_id'] = $this->_orderid;

        $this->_doc->packDetails('detaildata', $this->_itemlist);


        $this->_doc->amount = $this->docform->total->getText();

        $isEdited = $this->_doc->document_id > 0;


        $conn = \ZDB\DB::getConnect();
        $conn->BeginTrans();
        try {
            if ($this->_basedocid > 0) {
                $this->_doc->parent_id = $this->_basedocid;
                $this->_basedocid = 0;
            }
            $this->_doc->save();
            if ($sender->id == 'execdoc') {
                if (!$isEdited) {
                    $this->_doc->updateStatus(Document::STATE_NEW);
                }


                // проверка на минус  в  количестве

                $allowminus = System::getOption("common", "allowminus");
                if ($allowminus != 1) {

                    foreach ($this->_itemlist as $item) {
                        $qty = $item->getQuantity($this->_doc->headerdata['store']);
                        if ($qty < $item->quantity) {
                            $this->setError("nominus", H::fqty($qty), $item->item_name);
                            return;
                        }
                    }
                }


                $this->_doc->updateStatus(Document::STATE_EXECUTED);


            } else {

                $this->_doc->updateStatus($isEdited ? Document::STATE_EDITED : Document::STATE_NEW);

            }


            $conn->CommitTrans();
            if ($isEdited) {
                App::RedirectBack();
            } else {
                App::Redirect("\\App\\Pages\\Register\\GIList");
            }
        } catch(\Throwable $ee) {
            global $logger;
            $conn->RollbackTrans();
            $this->setError($ee->getMessage());

            $logger->error($ee->getMessage() . " Документ " . $this->_doc->meta_desc);
            return;
        }
    }

    public function onPayAmount($sender) {
        $this->docform->payamount->setText($this->docform->editpayamount->getText());
        $this->goAnkor("tankor");
    }


    public function onPayed($sender) {
        $this->docform->payed->setText(H::fa($this->docform->editpayed->getText()));
        $payed = $this->docform->payed->getText();
        $payamount = $this->docform->payamount->getText();
        if ($payed > $payamount) {

            $this->setWarn('inserted_extrasum');
        } else {
            $this->goAnkor("tankor");
        }
    }

    public function onPayDisc() {
        $this->docform->paydisc->setText($this->docform->editpaydisc->getText());
        $this->calcPay();
        $this->goAnkor("tankor");
    }

    /**
     * Расчет  итого
     *
     */
    private function calcTotal() {

        $total = 0;


        foreach ($this->_itemlist as $item) {
            $item->amount = $item->price * $item->quantity;

            $total = $total + $item->amount;

        }
        $this->docform->total->setText(H::fa($total));

        $disc = 0;

        $customer_id = $this->docform->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);

            if ($customer->discount > 0) {
                $disc = round($total * ($customer->discount / 100));
            } else {
                if ($customer->bonus > 0) {
                    if ($total >= $customer->bonus) {
                        $disc = $customer->bonus;
                    } else {
                        $disc = $total;
                    }
                }
            }
        }


        $this->docform->paydisc->setText($disc);
        $this->docform->editpaydisc->setText($disc);
    }

    private function calcPay() {
        $total = $this->docform->total->getText();
        $disc = $this->docform->paydisc->getText();

        if ($disc > 0) {
            $total -= $disc;
        }
        $p = $this->docform->payment->getValue() ;
        if($p >0 && $p != \App\Entity\MoneyFund::PREPAID ) {
            $this->docform->editpayamount->setText(H::fa($total));
            $this->docform->payamount->setText(H::fa($total));
        }
        if($p >0  && $p < 10000) {
            $this->docform->editpayed->setText(H::fa($total));
            $this->docform->payed->setText(H::fa($total));
        }
    }

    public function OnPayment($sender) {
        $this->docform->payed->setVisible(true);
        $this->docform->payamount->setVisible(true);
        $this->docform->paydisc->setVisible(true);

        $b = $sender->getValue();


        if ($b == \App\Entity\MoneyFund::PREPAID) {
            $this->docform->payed->setVisible(false);
            $this->docform->payamount->setVisible(false);
            $this->docform->paydisc->setVisible(false);
            $this->docform->payed->setText(0);
            $this->docform->editpayed->setText(0);
            $this->docform->payamount->setText(0);
            $this->docform->editpayamount->setText(0);
            $this->docform->paydisc->setText(0);
            $this->docform->editpaydisc->setText(0);
        }
        if ($b == \App\Entity\MoneyFund::CREDIT) {
            $this->docform->payed->setVisible(false);
            $this->docform->payed->setText(0);
            $this->docform->editpayed->setText(0);
        }
        $this->calcPay() ;
    }

    public function addcodeOnClick($sender) {
        $code = trim($this->docform->barcode->getText());
        $this->docform->barcode->setText('');
        if ($code == '') {
            return;
        }
        $store_id = $this->docform->store->getValue();
        if ($store_id == 0) {
            $this->setError('noselstore');
            return;
        }

        $code_ = Item::qstr($code);
        $item = Item::getFirst(" item_id in(select item_id from store_stock where store_id={$store_id}) and   (item_code = {$code_} or bar_code = {$code_})");


        if ($item == null) {

            $this->setWarn("noitemcode", $code);
            return;
        }


        $store_id = $this->docform->store->getValue();

        $qty = $item->getQuantity($store_id);
        if ($qty <= 0) {

            $this->setWarn("noitemonstore", $item->itemname);
        }


        if ($this->_itemlist[$item->item_id] instanceof Item) {
            $this->_itemlist[$item->item_id]->quantity += 1;
        } else {


            $price = $item->getPrice($this->docform->pricetype->getValue(), $store_id);
            $item->price = $price;
            $item->quantity = 1;

            if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

                $serial = '';
                $slist = $item->getSerials($store_id);
                if (count($slist) == 1) {
                    $serial = array_pop($slist);
                }


                if (strlen($serial) == 0) {
                    $this->setWarn('needs_serial');
                    $this->editdetail->setVisible(true);
                    $this->docform->setVisible(false);


                    $this->editdetail->edittovar->setKey($item->item_id);
                    $this->editdetail->edittovar->setText($item->itemname);
                    $this->editdetail->editserial->setText('');
                    $this->editdetail->editquantity->setText('1');
                    $this->editdetail->editprice->setText($item->price);


                    return;
                } else {
                    $item->snumber = $serial;
                }
            }
            $this->_itemlist[$item->item_id] = $item;
        }
        $this->docform->detail->Reload();
        $this->calcTotal();
        $this->calcPay();

        $this->_rowid = 0;
    }

    /**
     * Валидация   формы
     *
     */
    private function checkForm() {
        if (strlen($this->_doc->document_number) == 0) {

            $this->setError('enterdocnumber');
        }

        if (false == $this->_doc->checkUniqueNumber()) {
            $this->docform->document_number->setText($this->_doc->nextNumber());
            $this->setError('nouniquedocnumber_created');
        }

        if (count($this->_itemlist) == 0) {
            $this->setError("noenteritem");
        }

        if (($this->docform->store->getValue() > 0) == false) {
            $this->setError("noselstore");
        }
        $c = $this->docform->customer->getKey();

        $noallowfiz = System::getOption("common", "noallowfiz");
        if ($noallowfiz == 1 && $c == 0) {
            $this->setError("noselcust");
        }


        $p = $this->docform->payment->getValue();
        if ($p == 0) {
            $this->setError("noselpaytype");
        }
        if ($p == \App\Entity\MoneyFund::PREPAID && $c == 0) {
            $this->setError("mustsel_cust");
        }
        if ($this->_doc->payamount > $this->_doc->payed && $c == 0) {
            $this->setError("mustsel_cust");
        }
        if ($c == 0) {
            // $this->setError("noselcust");
        }

        return !$this->isError();
    }

    public function backtolistOnClick($sender) {
        App::RedirectBack();
    }

    public function OnChangeStore($sender) {
        //очистка  списка  товаров
        $this->_itemlist = array();
        $this->docform->detail->Reload();
    }

    public function OnChangeItem($sender) {
        $id = $sender->getKey();
        $item = Item::load($id);
        $store_id = $this->docform->store->getValue();

        $price = $item->getPrice($this->docform->pricetype->getValue(), $store_id);
        $qty = $item->getQuantity($store_id);

        $this->editdetail->qtystock->setText(H::fqty($qty));
        $this->editdetail->editprice->setText($price);
        if ($this->_tvars["usesnumber"] == true && $item->useserial == 1) {

            $serial = '';
            $slist = $item->getSerials($store_id);
            if (count($slist) == 1) {
                $serial = array_pop($slist);
            }
            $this->editdetail->editserial->setText($serial);
        }


        $this->updateAjax(array('qtystock', 'editprice', 'editserial'));
    }

    public function OnAutoItem($sender) {
        $store_id = $this->docform->store->getValue();
        $text = trim($sender->getText());
        return Item::findArrayAC($text);
    }

    public function OnAutoCustomer($sender) {
        return Customer::getList($sender->getText(), 1);
    }

    public function OnChangeCustomer($sender) {
        $this->docform->discount->setVisible(false);

        $customer_id = $this->docform->customer->getKey();
        if ($customer_id > 0) {
            $customer = Customer::load($customer_id);
            if ($customer->discount > 0) {
                $this->docform->discount->setText("Постоянная скидка " . $customer->discount . '%');
                $this->docform->discount->setVisible(true);
            } else {
                if ($customer->bonus > 0) {
                    $this->docform->discount->setText("Бонусы " . $customer->bonus);
                    $this->docform->discount->setVisible(true);
                }
            }

        }
        if ($this->_prevcust != $customer_id) {//сменился контрагент
            $this->_prevcust = $customer_id;
            $this->calcTotal();

            $this->calcPay();
        }
        $this->OnCustomerFirm(null);
    }

    //добавление нового контрагента
    public function addcustOnClick($sender) {
        $this->editcust->setVisible(true);
        $this->docform->setVisible(false);

        $this->editcust->editcustname->setText('');
        $this->editcust->editaddress->setText('');
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
        $cust->address = $this->editcust->editaddress->getText();
        $cust->phone = $this->editcust->editphone->getText();

        if (strlen($cust->phone) > 0 && strlen($cust->phone) != H::PhoneL()) {
            $this->setError("");
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
        $cust->type = Customer::TYPE_BAYER;
        $cust->save();
        $this->docform->customer->setText($cust->customer_name);
        $this->docform->customer->setKey($cust->customer_id);

        $this->editcust->setVisible(false);
        $this->docform->setVisible(true);
        $this->docform->discount->setVisible(false);

    }

    public function cancelcustOnClick($sender) {
        $this->editcust->setVisible(false);
        $this->docform->setVisible(true);
    }


    public function onOpenItemSel($sender) {
        $this->wselitem->setVisible(true);
        $this->wselitem->setPriceType($this->docform->pricetype->getValue());
        $this->wselitem->Reload();
    }

    public function onSelectItem($item_id, $itemname) {
        $this->editdetail->edittovar->setKey($item_id);
        $this->editdetail->edittovar->setText($itemname);
        $this->OnChangeItem($this->editdetail->edittovar);
    }

    public function OnCustomerFirm($sender) {
        $c = $this->docform->customer->getKey();
        $f = $this->docform->firm->getValue();

        $ar = \App\Entity\Contract::getList($c, $f);

        $this->docform->contract->setOptionList($ar);
        if (count($ar) > 0) {
            $this->docform->contract->setVisible(true);
        } else {
            $this->docform->contract->setVisible(false);
            $this->docform->contract->setValue(0);
        }

    }


    public function onPaste($sender) {
        $store_id = $this->docform->store->getValue();

        $cp = \App\Session::getSession()->clipboard;

        foreach ($cp as $it) {
            $item = Item::load($it->item_id);
            if ($item == null) {
                continue;
            }
            $item->quantity = 1;
            $item->price = $item->getPrice($this->docform->pricetype->getValue(), $store_id);

            $this->_itemlist[$item->item_id] = $item;
        }

        $this->docform->detail->Reload();


        $this->calcTotal();
        $this->calcPay();
    }

}





