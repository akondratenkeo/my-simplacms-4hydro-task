<?php

chdir('../..');
require_once('api/Simpla.php');
require_once('api/excel/PHPExcel.php'); //подключаем PHPExcel фреймворк


class ImportXmlAjax extends Simpla
{	
	private $import_files_dir      = 'simpla/files/import/'; // Временная папка
	private $import_file           = '';                     // Временный файл

	public function import()
	{
        $result = new stdClass();
        $result->error = 0;
        $result->msg = '';
        $this->import_file = $this->request->get('ifile');
        $from = $this->request->get('from');


        // Для корректной работы установим локаль UTF-8
        setlocale(LC_ALL, 'ru_RU.UTF-8');

        $f = fopen($this->import_files_dir.$this->import_file, 'ab');
        fwrite($f, file_get_contents('php://input'));
        fclose($f);

        if($this->get_extension($this->import_file) == 'xml') {

            // Дополняем объект результата
            $process_size = 3; // Размер всего процесса импорта

            $xml = simplexml_load_file($this->import_files_dir . $this->import_file);

            if (!isset($xml->HEADER) || !isset($xml->HEADER->SUPPLIER->SUPPLIER_NAME)) {
                $result->error = "XML-файл экспорта не соответствует стандарту";
                $from = $process_size;
            } else {

                // Парсим XML
                if(isset($xml->T_NEW_CATALOG)){

                    // Импорт категорий
                    if(isset($xml->T_NEW_CATALOG->CATALOG_GROUP_SYSTEM) && $from == 0){
                        $c_adds = $this->import_xml_categories($xml->T_NEW_CATALOG->CATALOG_GROUP_SYSTEM);
                        $result->msg = 'Добавленно категорий: '. $c_adds;
                    }

                    // Импорт товаров
                    if(isset($xml->T_NEW_CATALOG->ARTICLE) && $from == 1){
                        $i_adds = $this->import_xml_items($xml->T_NEW_CATALOG->ARTICLE);
                        $result->msg = 'Добавленно товаров: '. $i_adds;
                    }

                    // Привязка товаров к категориям
                    if(isset($xml->T_NEW_CATALOG->ARTICLE_TO_CATALOGGROUP_MAP) && $from == 2){
                        $m_adds = $this->mapping_xml_items($xml->T_NEW_CATALOG->ARTICLE_TO_CATALOGGROUP_MAP);
                        $result->msg = 'Привязано товаров: '. $m_adds;
                    }

                    $from = $from + 1;
                }else{
                    $result->error = "XML-файл экспорта не соответствует стандарту";
                    $from = $process_size;
                }
            }

        }elseif($this->get_extension($this->import_file) == 'xlsx'){

            // Дополняем объект результата
            $process_size = 1;  // Размер всего процесса импорта

            $xls_path = "" . $this->import_files_dir . $this->import_file;




            $objReader = new PHPExcel_Reader_Excel2007();
            $objReader->setReadDataOnly(true);
            $objPHPExcel = $objReader->load($xls_path);

            $ar = $objPHPExcel->getActiveSheet()->toArray();

            $from = $from + 1;  // На каком месте остановились
            $result->msg = 'Обновленно товаров: '. (count($ar)-1);
        }

		// Дополняем объект результата
        $result->from = $from;                  // На каком месте остановились
		$result->totalsize = $process_size;     // Размер всего процесса
        $result->end = ($result->from != $result->totalsize) ? 0 : 1;

        if($result->end)
            unlink($this->import_files_dir . $this->import_file);

        if($result->msg == '')
            $result->msg = 'Обновления отсутствуют';

        return $result;
	}

    private function import_xml_items($xml)
    {
        $id = null;
        $items_added = 0;
        $reference_eid = array();

        if(isset($xml) && count($xml) > 0){

            foreach($xml as $item)
            {
                // Проверим не пустое ли название и артинкул (должно быть хоть что-то из них)
                if(empty($item->SUPPLIER_AID) && empty($item->ARTICLE_DETAILS->DESCRIPTION_SHORT))
                    return false;

                // Подготовим товар для добавления в базу
                if(isset($item->ARTICLE_DETAILS->DESCRIPTION_SHORT)){
                    $product['name'] = trim($this->clear((string)$item->ARTICLE_DETAILS->DESCRIPTION_SHORT), '-');
                }else {
                    return false;
                }

                // Делаем товар видимым и задаем взаимосвязь
                $product['visible'] = 1;
                $product['featured'] = 0;

                // Добавляем описание товара
                if(isset($item->ARTICLE_DETAILS->DESCRIPTION_SHORT))
                    $product['annotation'] = (string)$item->ARTICLE_DETAILS->DESCRIPTION_SHORT;

                if(isset($item->ARTICLE_DETAILS->DESCRIPTION_LONG))
                    $product['body'] = (string)$item->ARTICLE_DETAILS->DESCRIPTION_LONG;

                $product['t_data'] = '';
                $product['m_info'] = '';

                // Заполняем meta-информацию
                $product['meta_title'] = $product['name'];

                if(isset($item->ARTICLE_DETAILS->DESCRIPTION_LONG))
                    $product['meta_description'] = $this->clear(substr((string)$item->ARTICLE_DETAILS->DESCRIPTION_LONG, 0, 160));

                $product['meta_keywords'] = '';
                if(isset($item->ARTICLE_DETAILS->KEYWORD)){
                    $keys = array();
                    foreach($item->ARTICLE_DETAILS->KEYWORD as $key_w){
                        $keys[] = $this->clear((string)$key_w);
                    }
                    if(count($keys) > 0)
                        $product['meta_keywords'] = $this->clear(substr(implode(', ', $keys), 0, 498));
                }

                // Заполняем поля параметров external_id, params
                $params = array();

                if(isset($item->ARTICLE_DETAILS->MANUFACTURER_AID))
                    $product['external_id'] = (string)$item->SUPPLIER_AID;

                if(isset($item->ARTICLE_DETAILS->EAN))
                    $params['EAN'] = (string)$item->ARTICLE_DETAILS->EAN;

                if(isset($item->ARTICLE_DETAILS->SUPPLIER_ALT_AID))
                    $params['S_ALT_AID'] = (string)$item->ARTICLE_DETAILS->SUPPLIER_ALT_AID;

                if(isset($item->ARTICLE_DETAILS->MANUFACTURER_AID))
                    $params['M_AID'] = (string)$item->ARTICLE_DETAILS->MANUFACTURER_AID;

                $product['params'] = json_encode($params);
                // -- end --

                $to_replace = array('-', '.', ' - ');
                $url_product_name = trim(str_replace($to_replace, '_', $product['name']), '_');

                // Формируем уникальный url
                $product['url'] = str_replace(' ', '_',$this->rus2translit($this->clear($url_product_name))).'_'.$product['external_id'];

                // Если задан бренд
                $item_brand = (string)$item->ARTICLE_DETAILS->MANUFACTURER_NAME;
                if(!empty($item_brand)){
                    // Найдем его по имени
                    $this->db->query('SELECT id FROM __brands WHERE name=?', $item_brand);
                    if(!$product['brand_id'] = $this->db->result('id'))
                        // Создадим, если не найден
                        $product['brand_id'] = $this->brands->add_brand(array('name'=>$item_brand, 'meta_title'=>$item_brand, 'meta_keywords'=>$item_brand, 'meta_description'=>$item_brand));
                }

                // Подготовим вариант товара
                if(isset($item->ARTICLE_DETAILS->MANUFACTURER_AID))
                    $variant['sku'] = (string)$item->ARTICLE_DETAILS->MANUFACTURER_AID;

                $variant['name'] = '';
                $variant['price'] = 1;
                $variant['stock'] = null;
                $variant['external_id'] = $product['external_id'];

                // Проверяем наличие экспортируемого товара
                if(!empty($product['external_id'])){

                    $this->db->query('SELECT id FROM __products WHERE external_id=? LIMIT 1', $product['external_id']);
                    $result = $this->db->result();
                    $product_id = 0;

                    if(!$result){

                        $product_id = $this->products->add_product($product);
                        $variant['product_id'] = $product_id;
                        $variant_id = $this->variants->add_variant($variant);
                        $items_added++;
                    }
                }

                if(isset($item->ARTICLE_FEATURES) && $product_id != 0){
                    // Параметров может быть несколько
                    $p_features = array( 'td' => array(), 'mi' => array() );
                    if(count($item->ARTICLE_FEATURES) > 1){
                        foreach($item->ARTICLE_FEATURES as $features){
                            if(count($features->FEATURE) > 1){
                                foreach($features->FEATURE as $feature){
                                    if(($feature->FDESCR == 'Technical Data') && (count($feature->FVALUE) == 1)){
                                        $p_features['td'][] = (isset($feature->FUNIT)) ? $feature->FNAME.':'.$feature->FVALUE.' '.$feature->FUNIT : $feature->FNAME.':'.$feature->FVALUE;
                                    }elseif($feature->FDESCR == 'Marketing Information'){
                                        $p_features['mi'][] = (count($feature->FVALUE) > 1) ? $feature->FNAME.':'.implode('|', (array)$feature->FVALUE) : $feature->FNAME.':'.$feature->FVALUE;
                                    }
                                }
                            }
                        }
                        $this->db->query("UPDATE __products SET t_data=?, m_info=? WHERE id=?", implode('#|', $p_features['td']), implode('#|', $p_features['mi']), $product_id);
                    }elseif(count($item->ARTICLE_FEATURES) == 1){
                        if(count($item->ARTICLE_FEATURES->FEATURE) > 1){
                            foreach($item->ARTICLE_FEATURES->FEATURE as $feature){
                                if(($feature->FDESCR == 'Technical Data') && (count($feature->FVALUE) == 1)){
                                    $p_features['td'][] = (isset($feature->FUNIT)) ? $feature->FNAME.':'.$feature->FVALUE.' '.$feature->FUNIT : $feature->FNAME.':'.$feature->FVALUE;
                                }elseif($feature->FDESCR == 'Marketing Information'){
                                    $p_features['mi'][] = (count($feature->FVALUE) > 1) ? $feature->FNAME.':'.implode('|', (array)$feature->FVALUE) : $feature->FNAME.':'.$feature->FVALUE;
                                }
                            }
                        }
                        $this->db->query("UPDATE __products SET t_data=?, m_info=? WHERE id=?", implode('#|', $p_features['td']), implode('#|', $p_features['mi']), $product_id);
                    }
                }

                if(isset($item->MIME_INFO->MIME) && $product_id != 0){
                    // Изображений может быть несколько
                    foreach($item->MIME_INFO->MIME as $image)
                    {
                        if($image->MIME_TYPE == 'image/jpeg')
                        {
                            // Имя файла
                            $image_filename = pathinfo((string)$image->MIME_SOURCE, PATHINFO_BASENAME);

                            // Добавляем изображение только если такого еще нет в этом товаре
                            $this->db->query('SELECT filename FROM __images WHERE product_id=? AND (filename=? OR filename=?) LIMIT 1', $product_id, $image_filename, (string)$image->MIME_SOURCE);
                            if(!$this->db->result('filename'))
                            {
                                $image_name = $product['name']. ' - ' .$image->MIME_DESCR;
                                $this->products->add_image($product_id, (string)$image->MIME_SOURCE, $image_name);
                            }
                        }
                    }
                }

                // Формируем массив сязанных товаров
                if(isset($item->ARTICLE_REFERENCE) && $product_id != 0){
                    foreach($item->ARTICLE_REFERENCE as $reference){
                        $reference_eid[$product_id][] = (string)$reference->ART_ID_TO;
                    }
                }
            }

            // Привязываем связанные товары к родительскому товару
            if(!empty($reference_eid) && count($reference_eid) > 0){

                foreach($reference_eid as $k => $v){
                    $eid_values = "'" . implode("','", $v) . "'";
                    $this->db->query("SELECT id FROM __products WHERE external_id IN (".$eid_values.")");
                    $r_results = $this->db->results();

                    if(count($r_results) > 0){
                        foreach($r_results as $rp_position => $rp_id){
                            $this->products->add_related_product($k, $rp_id->id, $rp_position);
                        }
                    }
                }
            }
        }
        return $items_added;
    }

    // Отдельная функция для импорта категории из xml-файла
    private function import_xml_categories($xml)
    {
        $id = null;
        $category_map = array();

        if(isset($xml->CATALOG_STRUCTURE)){
            // Для каждой категории
            foreach($xml->CATALOG_STRUCTURE as $category)
            {
                if(!empty($category) && $category->PARENT_ID != 0)
                {
                    // Найдем категорию по ID
                    $this->db->query('SELECT id FROM __categories WHERE external_id=?', (int)$category->GROUP_ID);
                    $id = $this->db->result('id');

                    // Если не найдена - добавим ее
                    if(empty($id))
                    {
                        $parent = (int)$category->PARENT_ID != 1 ? (int)$category->PARENT_ID : 140;
                        $cat_params = array(
                            'parent_id'=>$parent,
                            'external_id'=>(int)$category->GROUP_ID,
                            'name'=>(string)$category->GROUP_NAME,
                            'meta_title'=>(string)$category->GROUP_NAME,
                            'meta_keywords'=>(string)$category->GROUP_NAME,
                            'meta_description'=>(string)$category->GROUP_NAME,
                            'description'=>'<p>'.(string)$category->GROUP_NAME.'</p>',
                            'position'=>(int)$category->GROUP_ORDER
                        );
                        $id = $this->categories->add_category($cat_params);
                        $category_map[] = array('id' => $id, 'external_id' => $cat_params['external_id']);
                    }
                }
            }

            // Переопредиление родительского значения
            if(count($category_map) > 0) {
                $this->category_mapping($category_map);
            }
        }
        return count($category_map);
    }

    // Перезаписываем значения колонки parent_id
    private function category_mapping($category_map){
        if(count($category_map) > 0) {
            foreach ($category_map as $cm) {
                $this->db->query("UPDATE __categories SET parent_id=? WHERE parent_id=?", $cm['id'], $cm['external_id']);
            }
        }
    }

    // Перезаписываем значения колонки parent_id
    private function mapping_xml_items($map){
        $i = 0;
        foreach($map as $map_item){
            $this->db->query("SELECT id FROM __categories WHERE external_id=?", (string)$map_item->CATALOG_GROUP_ID);
            $c_result = $this->db->result();

            $this->db->query("SELECT id FROM __products WHERE external_id=?", (string)$map_item->ART_ID);
            $p_result = $this->db->result();

            $this->categories->add_product_category($p_result->id, $c_result->id);
            $i++;
        }
        return $i;
    }

    private function get_extension($name, $p_flag = 0)
    {
        $pos = strrpos($name, '.');
        $l_ext = strtolower(substr($name, $pos));

        if(!$p_flag){
            $ext = substr($l_ext, 1);
        }else{
            $ext = $l_ext;
        }

        return $ext;
    }

    private function clear($val)
    {
        return strval(preg_replace('/[^\p{L}\p{Nd}\d\s_\-\.\%\s]/ui', '', trim($val)));
    }

    private function rus2translit($string) {
        $converter = array(
            'а' => 'a',   'б' => 'b',   'в' => 'v',
            'г' => 'g',   'д' => 'd',   'е' => 'e',
            'ё' => 'e',   'ж' => 'zh',  'з' => 'z',
            'и' => 'i',   'й' => 'y',   'к' => 'k',
            'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',
            'с' => 's',   'т' => 't',   'у' => 'u',
            'ф' => 'f',   'х' => 'h',   'ц' => 'c',
            'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',
            'ь' => '',  'ы' => 'y',   'ъ' => '',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            'ä' => 'a',

            'А' => 'A',   'Б' => 'B',   'В' => 'V',
            'Г' => 'G',   'Д' => 'D',   'Е' => 'E',
            'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',
            'И' => 'I',   'Й' => 'Y',   'К' => 'K',
            'Л' => 'L',   'М' => 'M',   'Н' => 'N',
            'О' => 'O',   'П' => 'P',   'Р' => 'R',
            'С' => 'S',   'Т' => 'T',   'У' => 'U',
            'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
            'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',
            'Ь' => '',  'Ы' => 'Y',   'Ъ' => '',
            'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',
        );
        return strtolower(strtr($string, $converter));
    }
}


class chunkReadFilter implements PHPExcel_Reader_IReadFilter
{
    private $_startRow = 0;
    private $_endRow = 0;

    public function setRows($startRow, $chunkSize) {
        $this->_startRow    = $startRow;
        $this->_endRow      = $startRow + $chunkSize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow)) {
            return true;
        }
        return false;
    }
}

$import_xml_ajax = new ImportXmlAjax();
header("Content-type: application/json; charset=UTF-8");
header("Cache-Control: must-revalidate");
header("Pragma: no-cache");
header("Expires: -1");

$json = json_encode($import_xml_ajax->import());
echo $json;