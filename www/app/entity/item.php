<?php

namespace App\Entity;

/**
 * Клас-сущность  ТМЦ
 *
 * @table=items
 * @view=items_view
 * @keyfield=item_id
 */
class Item extends \ZCL\DB\Entity
{
    const TYPE_TOVAR    = 1;   //товар
    const TYPE_MAT      = 2;   //материалы  и комплектующие
    const TYPE_MBP      = 3;   //малоценка
    const TYPE_PROD     = 4;   //готовая продукция
    const TYPE_HALFPROD = 5;   //полуфабрикаты


    private $brprice = array(); //цены по  филиалам

    protected function init() {
        $this->item_id = 0;
        $this->cat_id = 0;

        $this->price = 0;
        $this->image_id = 0;
        $this->pricelist = 1;
    }

    protected function afterLoad() {


        $xml = @simplexml_load_string($this->detail);

        $this->price1 = (string)($xml->price1[0]);
        $this->price2 = (string)($xml->price2[0]);
        $this->price3 = (string)($xml->price3[0]);
        $this->price4 = (string)($xml->price4[0]);
        $this->price5 = (string)($xml->price5[0]);
        $this->rate = (string)($xml->rate[0]);
        $this->val = (string)($xml->val[0]);
        $this->zarp = (string)($xml->zarp[0]);

        $this->pricelist = (int)$xml->pricelist[0];
        $this->useserial = (int)$xml->useserial[0];
        $this->image_id = (int)$xml->image_id[0];

        $this->weight = (string)$xml->weight[0];
        $this->manufacturer = (string)$xml->manufacturer[0];
        $this->shortname = (string)$xml->shortname[0];
        $this->warranty = (string)$xml->warranty[0];

        $this->cell = (string)$xml->cell[0];
        //  $this->octoreoptions = (string) $xml->octoreoptions[0];
        $brprice = (string)$xml->brprice[0];

        $this->brprice = @unserialize($brprice);
        if (!is_array($this->brprice)) {
            $this->brprice = array();
        }

        $id = \App\System::getBranch();
        if ($id > 0 && is_array($this->brprice[$id])) {
            $this->price1 = $this->brprice[$id]['price1'];
            $this->price2 = $this->brprice[$id]['price2'];
            $this->price3 = $this->brprice[$id]['price3'];
            $this->price4 = $this->brprice[$id]['price4'];
            $this->price5 = $this->brprice[$id]['price5'];
        }


        parent::afterLoad();
    }

    protected function beforeSave() {
        parent::beforeSave();
        $fid = \App\System::getBranch();
        if ($fid > 0) {
            $this->brprice[$fid] = array('price1' => $this->price1, 'price2' => $this->price2, 'price3' => $this->price3, 'price4' => $this->price4, 'price5' => $this->price5);
            $prev = self::load($this->item_id); //востанавливаем  предыдущую цену
            $this->price1 = $prev->price1;
            $this->price2 = $prev->price2;
            $this->price3 = $prev->price3;
            $this->price4 = $prev->price4;
            $this->price5 = $prev->price5;
        }
        $this->detail = "<detail>";
        //упаковываем  данные в detail
        $this->detail .= "<pricelist>{$this->pricelist}</pricelist>";
        $this->detail .= "<useserial>{$this->useserial}</useserial>";

        $this->detail .= "<cell>{$this->cell}</cell>";
        //   $this->detail .= "<octoreoptions><![CDATA[{$this->octoreoptions}]]></octoreoptions>";
        $this->detail .= "<manufacturer><![CDATA[{$this->manufacturer}]]></manufacturer>";
        $this->detail .= "<shortname><![CDATA[{$this->shortname}]]></shortname>";
        $this->detail .= "<warranty><![CDATA[{$this->warranty}]]></warranty>";

        $this->detail .= "<price1>{$this->price1}</price1>";
        $this->detail .= "<price2>{$this->price2}</price2>";
        $this->detail .= "<price3>{$this->price3}</price3>";
        $this->detail .= "<price4>{$this->price4}</price4>";
        $this->detail .= "<price5>{$this->price5}</price5>";
        $this->detail .= "<val>{$this->val}</val>";
        $this->detail .= "<rate>{$this->rate}</rate>";
        $this->detail .= "<zarp>{$this->zarp}</zarp>";

        $this->detail .= "<image_id>{$this->image_id}</image_id>";
        $this->detail .= "<weight>{$this->weight}</weight>";


        //упаковываем  цены  по  филиалам
        $brprice = serialize($this->brprice);


        $this->detail .= "<brprice><![CDATA[{$brprice}]]></brprice>";


        $this->detail .= "</detail>";

        return true;
    }

    public function allowDelete() {

        $conn = \ZDB\DB::getConnect();
        //проверка на партии
        $sql = "  select count(*)  from  store_stock where   item_id = {$this->item_id}";
        $cnt = $conn->GetOne($sql);
        return ($cnt > 0) ? false : true;
    }

    protected function afterDelete() {


        if ($this->image_id > 0) {
            \App\Entity\Image::delete($this->image_id);
        }
    }

    //Вычисляет  отпускную цену
    //$_price - цифра (заданая цена) или  наименование  цены из настроек 
    //$store - склад
    //$partion - партия
    public function getPrice($_price_, $store = 0, $partion = 0) {
        $price = 0;
        $_price = 0;
        $common = \App\System::getOptions("common");
        if (strlen($common[$_price_]) == 0) {
            return 0;
        }


        if ($_price_ == 'price1') {
            $_price = $this->price1;
        } else {
            if ($_price_ == 'price2') {
                $_price = $this->price2;
            } else {
                if ($_price_ == 'price3') {
                    $_price = $this->price3;
                } else {
                    if ($_price_ == 'price4') {
                        $_price = $this->price4;
                    } else {
                        if ($_price_ == 'price5') {
                            $_price = $this->price5;
                        }
                    }
                }
            }
        }


        //если процент    
        if (strpos($_price, '%') > 0) {

            $ret = doubleval(str_replace('%', '', $_price));
            if ($ret > 0) {
                if ($partion == 0) {
                    //ищем последнюю закупочную  цену 
                    $partion = $this->getLastPartion($store);
                }
                $price = $partion + (int)$partion / 100 * $ret;
                //курсовая разница
                $opv = \App\System::getOptions("val");
                if (strlen($this->val) > 1 && $opv['valprice'] == 1) {
                    $k = $opv[$this->val] / $this->rate;
                    $price = $price * $k;
                }


            }
        } else {
            if ($_price > 0) {
                $price = $_price; //задана  просто  цифра
            }
        }

        if ($price == 0 && $this->cat_id > 0) {
            $cat = \App\Entity\Category::load($this->cat_id);
            if ($cat != null) {
                if ($partion == 0) {
                    //ищем последнюю закупочную  цену 
                    $partion = $this->getLastPartion($store);
                }
                if ($_price_ == 'price1' && $cat->price1 > 0) {
                    $price = $partion + (int)$partion / 100 * $cat->price1;
                }
                if ($_price_ == 'price2' && $cat->price2 > 0) {
                    $price = $partion + (int)$partion / 100 * $cat->price2;
                }
                if ($_price_ == 'price3' && $cat->price3 > 0) {
                    $price = $partion + (int)$partion / 100 * $cat->price3;
                }
                if ($_price_ == 'price4' && $cat->price4 > 0) {
                    $price = $partion + (int)$partion / 100 * $cat->price4;
                }
                if ($_price_ == 'price5' && $cat->price5 > 0) {
                    $price = $partion + (int)$partion / 100 * $cat->price5;
                }

                //курсовая разница
                $opv = \App\System::getOptions("val");
                if (strlen($this->val) > 1 && $opv['valprice'] == 1) {
                    $k = $opv[$this->val] / $this->rate;
                    $price = $price * $k;
                }

            }
        }


        //если не  задано используем глобальную наценку
        if ($common['defprice'] > 0 && $price == 0) {

            if ($partion == 0) {
                //ищем последнюю закупочную  цену 
                $partion = $this->getLastPartion($store);
            }

            $price = $partion + (int)$partion / 100 * $common['defprice'];
            //курсовая разница
            $opv = \App\System::getOptions("val");
            if (strlen($this->val) > 1 && $opv['valprice'] == 1) {
                $k = $opv[$this->val] / $this->rate;
                $price = $price * $k;
            }

        }


        return \App\Helper::fa($price);
    }

    public function getLastPartion($store = 0, $snumber = "") {
        $conn = \ZDB\DB::getConnect();
        $sql = "  select coalesce(partion,0)  from  store_stock where partion >0 and    item_id = {$this->item_id}   ";
        if ($store > 0) {
            $sql = $sql . " and store_id=" . $store;
        }
        if (strlen($snumber) > 0) {
            $sql .= "  and  snumber =  " . $conn->qstr($snumber);
        }

        $sql = $sql . " order  by  stock_id desc limit 0,1";

        return $conn->GetOne($sql);
    }

    public static function getPriceTypeList() {

        $common = \App\System::getOptions("common");
        $list = array();
        if (strlen($common['price1']) > 0) {
            $list['price1'] = $common['price1'];
        }
        if (strlen($common['price2']) > 0) {
            $list['price2'] = $common['price2'];
        }
        if (strlen($common['price3']) > 0) {
            $list['price3'] = $common['price3'];
        }
        if (strlen($common['price4']) > 0) {
            $list['price4'] = $common['price4'];
        }
        if (strlen($common['price5']) > 0) {
            $list['price5'] = $common['price5'];
        }

        return $list;
    }

    /**
     * возвращает количество на складах
     *
     * @param mixed $item_id
     * @param mixed $store_id
     * @param mixed $snumber партия проиводителя
     */
    public function getQuantity($store_id = 0, $snumber = "") {

        $conn = \ZDB\DB::getConnect();
        $sql = "  select coalesce(sum(qty),0) as qty  from  store_stock_view where   item_id = {$this->item_id} ";
        if ($store_id > 0) {
            $sql .= " and store_id = " . $store_id;
        }
        if (strlen($snumber) > 0) {
            $sql .= " and  snumber = " . $conn->qstr($snumber);
        }
        $cnt = $conn->GetOne($sql);
        return $cnt;
    }

    /**
     * возвращает список скенрий производителя
     *
     * @param mixed $store_id
     */
    public function getSerials($store_id = 0) {

        $conn = \ZDB\DB::getConnect();
        $sql = "  select snumber  from  store_stock_view where   item_id = {$this->item_id} and qty >0 and snumber <>'' and snumber is not null ";
        if ($store_id > 0) {
            $sql .= " and store_id = " . $store_id;
        }


        $res = $conn->Execute($sql);
        $list = array();
        foreach ($res as $row) {
            if (strlen($row['snumber']) > 0) {
                $list[] = $row['snumber'];
            }
        }
        return $list;
    }

    /**
     * возвращает сумму на складах
     *
     * @param mixed $item_id
     * @param mixed $store_id
     */
    public function getAmount($store_id = 0) {

        $conn = \ZDB\DB::getConnect();
        $sql = "  select coalesce(sum(qty*partion),0) as amount  from  store_stock_view where   item_id = {$this->item_id} ";
        if ($store_id > 0) {
            $sql .= " and store_id = " . $store_id;
        }
        $amount = $conn->GetOne($sql);
        return $amount;
    }

    /**
     * Метод  для   получения  имени  ТМЦ   для выпадающих списков
     *
     * @param mixed $criteria
     * @return []
     * @static
     */
    public static function findArrayAC($partname, $store = 0, $cat = 0) {

        $criteria = "  disabled <> 1 ";
        if ($store > 0) {
            $criteria .= "     and item_id in (select item_id from store_stock  where  store_id={$store})";
        }
        if ($cat > 0) {
            $criteria .= "  and cat_id={$cat}    and item_id in (select item_id from store_stock  where  store_id={$store})";
        }

        if (strlen($partname) > 0) {
            $like = self::qstr('%' . $partname . '%');
            $partname = self::qstr($partname);
            $criteria .= "  and  (itemname like {$like} or item_code like {$like}   or   bar_code like {$like} )";
        }

        $itemlist = self::find($criteria);

        $list = array();
        foreach ($itemlist as $key => $value) {

            $list[$key] = $value->itemname;
            if (strlen($value->manufacturer) > 0) {
                $list[$key] = $value->itemname . ' (' . $value->manufacturer . ')';
            }
        }

        return $list;
    }

    /**
     * генерирует новый артикул
     *
     */
    public static function getNextArticle() {
        $conn = \ZDB\DB::getConnect();

        $sql = "  select max(item_id)  from  items ";
        $id = $conn->GetOne($sql);

        return "ID" . sprintf("%04d", ++$id);
    }


    public static function getManufacturers() {

        $conn = \ZDB\DB::getConnect();

        $sql = "  select distinct manufacturer from  items    order  by manufacturer";
        $res = $conn->Execute($sql);
        $list = array();
        foreach ($res as $v) {
            if (strlen($v['manufacturer']) > 0) {
                $list[] = $v['manufacturer'];
            }
        }
        return $list;

    }


    public static function getTypes() {
        $list = array();

        $list[Item::TYPE_TOVAR] = \App\Helper::l('it_tovar');
        $list[Item::TYPE_MAT] = \App\Helper::l('it_mat');
        $list[Item::TYPE_MBP] = \App\Helper::l('it_mbp');
        $list[Item::TYPE_PROD] = \App\Helper::l('it_prod');
        $list[Item::TYPE_HALFPROD] = \App\Helper::l('it_hprod');


        return $list;
    }

    /**
    * себестоимость  для  готовой продукции
    * 
    */
    public function  getProdprice(){
        $price = 0;
        if ($this->zarp > 0) {
            $price += $this->zarp;
        }
        $ilist = \App\Entity\ItemSet::find("pitem_id=" . $this->item_id);

        if (count($ilist) > 0) {
            foreach ($ilist as $iset) {
                $it = \App\Entity\Item::load($iset->item_id);
                $pr = $it->getLastPartion(0);
                $price += ($iset->qty * $pr);
            }
        } 
        
        
        return  $price;     
    }
}
