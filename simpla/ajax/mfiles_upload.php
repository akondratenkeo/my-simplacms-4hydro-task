<?php
/**
 * Created by PhpStorm.
 * User: Alex
 * Date: 01.09.2015
 * Time: 18:28
 */

chdir('../..');
require_once('api/Simpla.php');
require_once('simpla/pclzip/pclzip.lib.php'); //подключаем PHPExcel фреймворк

$simpla = new Simpla();

function CheckExist($p_event, &$p_header) {

    global $simpla;
    //static $i;

    if ($p_header['status'] == 'ok') {
        $original_img_path = $simpla->config->original_images_dir;
        $resized_img_path = $simpla->config->resized_images_dir;

        $info = pathinfo($p_header['filename']);

        if (file_exists($original_img_path.$info['basename'])) {
            unlink($original_img_path.$info['basename']);
        }
        if (file_exists($resized_img_path.$info['filename'].'35x35'.$info['extension'])) {
            unlink($resized_img_path.$info['filename']);
        }
        if (file_exists($resized_img_path.$info['filename'].'100x100'.$info['extension'])) {
            unlink($resized_img_path.$info['filename']);
        }
        if (file_exists($resized_img_path.$info['filename'].'150x150'.$info['extension'])) {
            unlink($resized_img_path.$info['filename']);
        }
        if (file_exists($resized_img_path.$info['filename'].'220x220'.$info['extension'])) {
            unlink($resized_img_path.$info['filename']);
        }
        if (file_exists($resized_img_path.$info['filename'].'280x280'.$info['extension'])) {
            unlink($resized_img_path.$info['filename']);
        }
        //$i++;
        return 1;
    } else {
        return 0;
    }
}

function ResizePhoto($p_event, &$p_header) {
    if ($p_header['status'] == 'ok') {
        return 1;
    } else {
        return 0;
    }
}

$upload_dir = "simpla/files/import";
$hash = $_SERVER["HTTP_UPLOAD_ID"];
$zip_file_name = "files/import"."/".$hash.".zip";


if (preg_match("/^[0123456789a-z]{32}$/i", $hash)) {

    if ($_SERVER["REQUEST_METHOD"] == "GET") {
        if ($_GET["action"] == "abort") {
            if (is_file($upload_dir."/".$hash.".html5upload")) {
                unlink($upload_dir."/".$hash.".html5upload");
            }
            print "ok abort";
            return;
        }

        if ($_GET["action"] == "done") {
            if (is_file($upload_dir."/".$hash.".original")) {
                unlink($upload_dir."/".$hash.".original");
            }

            rename($upload_dir."/".$hash.".html5upload", $zip_file_name);

            $fw = fopen($upload_dir."/".$hash.".original_ready", "wb");
            if ($fw) {
                fclose($fw);
                unlink($upload_dir."/".$hash.".original_ready");
            }

        }

        if ($_GET["action"] == "zip") {

            $ext = substr($zip_file_name, strrpos($zip_file_name, '.') + 1);

            if (($ext == "zip")) {

                $archive = new PclZip($zip_file_name);
                $list = $archive->extract(PCLZIP_OPT_PATH, $simpla->config->original_images_dir, PCLZIP_CB_PRE_EXTRACT, 'CheckExist');

                if ($list == 0) {
                    //unlink($zip_file_name);
                    header("HTTP/1.0 500 Internal Server Error");
                    print "Ошибка архивации : ".$archive->errorInfo(true);

                } else {
                    header("HTTP/1.0 200 OK");
                    print "ok\n";
                }

                unlink($zip_file_name);
            } else {
                header("HTTP/1.0 500 Internal Server Error");
                print "Вы указали неправильный формат файла!";
            }
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST") {

        $filename = $upload_dir."/".$hash.".html5upload";

        if (intval($_SERVER["HTTP_PORTION_FROM"]) == 0) {
            $fout = fopen($filename,"wb");
        }
        else
            $fout = fopen($filename,"ab");

        if (!$fout) {
            header("HTTP/1.0 500 Internal Server Error");
            print "Can't open file for writing";
            return;
        }

        $fin = fopen("php://input", "rb");
        if ($fin) {
            while (!feof($fin)) {
                $data = fread($fin, 1024*1024);
                fwrite($fout, $data);
            }
            fclose($fin);
        }
        fclose($fout);
    }

    header("HTTP/1.0 200 OK");
    print "ok\n";
}
else {
    header("HTTP/1.0 500 Internal Server Error");
    print "Wrong session hash";
}