{* Вкладки *}
{capture name=tabs}
	<li><a href="index.php?module=ImportAdmin">Импорт</a></li>
    <li class="active"><a href="index.php?module=ImportXmlAdmin">Импорт XML, XLS</a></li>
	<li><a href="index.php?module=ExportAdmin">Экспорт</a></li>		
	<li><a href="index.php?module=BackupAdmin">Бекап</a></li>
    <li><a href="index.php?module=MassImgImportAdmin">Импорт Медиа-файлов</a></li>
    {*<li><a href="index.php?module=ExportContactsAdmin">Экспорт контактов</a></li>*}
{/capture}
{$meta_title='Импорт товаров из XML, XLS' scope=parent}

<script>
{if $filename}
{literal}
	
	var in_process=false;
	var count=1;
    var import_file='{/literal}{$f_import_name}{literal}';

	// On document load
	$(function(){
    	$("#progressbar").progressbar({ value: 1 });
		in_process=true;
		do_import();
        $('div#m_error').empty();
	});
  
	function do_import(from)
	{
        from = typeof(from) != 'undefined' ? from : 0;
		$.ajax({
 			 url: "ajax/import_xml.php",
 			 	data: {
                    from:from,
                    ifile:import_file
                },
 			 	dataType: 'json',
  				success: function(data){
                    if (data.error) {
                        $('div#m_error').hide();
                        $('div#m_error').prepend('<div class="message message_error"><span>' + data.error + '</span></div>').show(1500);

                        $("#progressbar").hide('fast');
                        in_process = false;

                        $('div#try_again').prepend('<p><a href="/simpla/index.php?module=ImportXmlAdmin">Загрузить другой файл</a></p>').show();
                    } else {
                        if(data.msg != '') {
                            if(data.from == 1){
                                $('div#import_result').show();
                            }
                            $('div#import_result').append('<p>' + data.msg + '</p>');
                        }

                        $("#progressbar").progressbar({value: 100 * data.from / data.totalsize});

                        if (data != false && !data.end) {
                            do_import(data.from);
                        }
                        else {
                            $("#progressbar").hide('fast');
                            in_process = false;
                            $('div#import_result').append('<p><a href="/simpla/index.php?module=ProductsAdmin">Новые позиции доступны в общем каталоге</a></p>');
                        }
                    }
  				},
				error: function(xhr, status, errorThrown) {
                	alert(errorThrown+'\n'+status+'\n'+xhr.statusText);
        		}  				
		});
	
	} 
{/literal}
{/if}
</script>

<style>
	.ui-progressbar-value { background-color:#b4defc; background-image: url(design/images/progress.gif); background-position:left; border-color: #009ae2;}
	#progressbar{ clear: both; height:29px;}
    #try_again{ display: none; padding: 20px 0 20px 0}
</style>

<div id="m_error">
{if $message_error}
<!-- Системное сообщение -->
<div class="message message_error">
	<span>
	{if $message_error == 'no_permission'}Установите права на запись в папку {$import_files_dir}
    {elseif $message_error == 'no_file_error'}Не указан файл импорта
    {elseif $message_error == 'format_error'}Не допустимый формат файла импорта
    {elseif $message_error == 'size_error'}Загружаемый файл превышает - 16 МБ
	{elseif $message_error == 'upload_error'}Ошибка загрузки файла импорта
    {elseif $message_error == 'convert_error'}Не получилось сконвертировать файл в кодировку UTF8
	{elseif $message_error == 'locale_error'}На сервере не установлена локаль {$locale}, импорт может работать некорректно
	{else}{$message_error}{/if}
	</span>
</div>
<!-- Системное сообщение (The End)-->
{/if}
</div>

	{if $message_error != 'no_permission'}
	
	{if $filename}
	<div style="margin-bottom: 20px;">
		<h1>Импорт {$filename|escape}</h1>
	</div>
	<div id='progressbar'></div>
    <div id='try_again' class="block"></div>
	<div id='import_result' class="block block_help" style="display: none;"></div>
	{else}
	
		<h1>Импорт товаров из XML, XLS</h1>

		<div class="block">	
            <form method=post id=product enctype="multipart/form-data">
                <input type=hidden name="session_id" value="{$smarty.session.id}">
                <input name="file" class="import_file" type="file" value="" />
                <input class="button_green" type="submit" name="" value="Загрузить" />
                <p>
                    (максимальный размер файла &mdash; {if $config->max_upload_filesize>1024*1024}{$config->max_upload_filesize/1024/1024|round:'2'} МБ{else}{$config->max_upload_filesize/1024|round:'2'} КБ{/if})
                </p>
            </form>
		</div>
        <div id='import_result' class="block block_help" style="display: none;"></div>
		<!--<div class="block block_help">
		<p>
			Создайте бекап на случай неудачного импорта. 
		</p>
		<p>
			Сохраните таблицу в формате CSV
		</p>
		<p>
			<a href='files/import/example.csv'>Скачать пример файла</a>
		</p>
		</div>-->
	
	{/if}

{/if}