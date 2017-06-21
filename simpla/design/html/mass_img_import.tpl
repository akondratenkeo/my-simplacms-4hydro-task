{* Вкладки *}
{capture name=tabs}
	<li><a href="index.php?module=ImportAdmin">Импорт</a></li>
    <li><a href="index.php?module=ImportXmlAdmin">Импорт XML, XLS</a></li>
	<li><a href="index.php?module=ExportAdmin">Экспорт</a></li>		
	<li><a href="index.php?module=BackupAdmin">Бекап</a></li>
    <li class="active"><a href="index.php?module=MassImgImportAdmin">Импорт Медиа-файлов</a></li>
{/capture}
{$meta_title='Импорт медиа-файлов' scope=parent}

<style>
    .progress { position: relative; margin-top: 15px; width: 100%; min-height: 15px; clear: both; }
    #progressbar { position: absolute; display: none; width: 100%; height: 28px; font-family: sans-serif; font-size: 12px; line-height: 25px; text-align: center; color: #000; border: 1px solid #aaa; }
    #progress_complete {  display: none; width: 0; height: 29px; font-family: sans-serif; font-size: 12px; line-height: 29px; text-align: center; color: #fff; background-color: #b4defc; background-image: url('design/images/progress.gif'); background-position: left; border-color: #009ae2; }
</style>

<div id="m_error">
    {if $message_error}
    <!-- Системное сообщение -->
    <div class="message message_error">
        <span>
        {if $message_error == 'no_permission'}Установите права на запись в папку {$import_files_dir}
        {else}{$message_error}{/if}
        </span>
    </div>
    <!-- Системное сообщение (The End)-->
    {/if}
</div>

{if $message_error != 'no_permission'}

    <h1>Импорт медиа-файлов</h1>

    <div class="progress">
        <div id='progressbar'></div>
        <div id='progress_complete'></div>
    </div>

    <div class="block">
        <form method="post" id="media_files_upload" onsubmit="return false" style="display: none;">
            <input type=hidden name="session_id" value="{$hash}">
            <input type="file" name="files[]" id="files" class="import_file" value="" />
            <input type="submit" class="button_green" value="Загрузить" />
            <p>(максимальный размер файла &mdash; 2Гб)</p>
        </form>
    </div>

    <div id='import_result' class="block block_help" style="display: none;"></div>

    <script>
    {literal}
        // On document load
        $(function() {
            show_form();
        });

        function show_form() {
            var files_uploader = new FileUploader({
                message_error:      'Ошибка при загрузке файла',
                form:               'media_files_upload',
                formfiles:          'files',
                uploadid:           '{/literal}{$hash}{literal}',
                uploadscript:       'ajax/mfiles_upload.php',
                portion:            2097152
            });

            if (!files_uploader) {
                document.location = '/';
            } else {
                if (!files_uploader.CheckBrowser()) {
                    document.location = '/';
                } else {
                    var mf_form = $('#media_files_upload');
                    if (mf_form) {
                        mf_form.show();
                    }
                }
            }
        }
    {/literal}
    </script>

{/if}