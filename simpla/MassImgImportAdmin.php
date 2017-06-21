<?PHP
require_once('api/Simpla.php');

class MassImgImportAdmin extends Simpla
{	
	public $import_files_dir = 'simpla/files/import/';
	public $allowed_extensions = array('zip');

	public function fetch()
	{
		$this->design->assign('import_files_dir', $this->import_files_dir);

		// Проверяем возможность записи в папку
        if(!is_writable($this->import_files_dir))
			$this->design->assign('message_error', 'no_permission');

        $hash = md5(time(true));
        $this->design->assign('hash', $hash);

		return $this->design->fetch('mass_img_import.tpl');
	}
}

