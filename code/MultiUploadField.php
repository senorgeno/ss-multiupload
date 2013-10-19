<?php


/**
 * A simple front end multi upload field for SS 3.1.X
 *
 * @author Gene
 */
class MultiUploadField extends FileField {
	/**
	 * Items loaded into this field. May be a RelationList, or any other SS_List
	 * 
	 * @var SS_List
	 */
	protected $items;
	
	/**
	 * Parent data record. Will be infered from parent form or controller if blank.
	 * 
	 * @var DataObject
	 */
	protected $record;
	
//	public function __construct($name, $title = null, SS_List $items = null) {
//
//		parent::__construct($name, $title);
//
//		if($items) $this->setItems($items);
//
//		// filter out '' since this would be a regex problem on JS end
//		$this->getValidator()->setAllowedExtensions(
//			array_filter(Config::inst()->get('File', 'allowed_extensions'))
//		); 
//		
//	}
	
	public function Field($properties = array()) {
		
		$folder = 'multiupload';
//		Requirements::css('http://netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css');
//		Requirements::css($folder.'/css/jquery.fileupload.css');
//		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
//		Requirements::javascript($folder . '/js/vendor/jquery.ui.widget.js');
//		Requirements::javascript($folder . '/js/jquery.iframe-transport.js');
//		Requirements::javascript($folder. '/js/jquery.fileupload.js');
//		Requirements::customScript(<<<JS
//  $(function () {
//    'use strict';
//    // Change this to the location of your server-side upload handler:
//    var url = window.location.hostname === 'blueimp.github.io' ?
//                '//jquery-file-upload.appspot.com/' : 'server/php/';
//    jQuery('#fileupload').fileupload({
//        url: url,
//        dataType: 'json',
//        done: function (e, data) {
//            jQuery.each(data.result.files, function (index, file) {
//                jQuery('<p/>').text(file.name).appendTo('#files');
//            });
//        },
//        progressall: function (e, data) {
//            var progress = parseInt(data.loaded / data.total * 100, 10);
//            $('#progress .progress-bar').css(
//                'width',
//                progress + '%'
//            );
//        }
//    }).prop('disabled', !jQuery.support.fileInput)
//        .parent().addClass(jQuery.support.fileInput ? undefined : 'disabled');
//});
//JS
//);
			  
		$this->setTemplate('MultiUpload_Field');
		return parent::Field($properties);
		
	}
	
	public function getAttributes() {
		$attrs = array(
			'type' => 'file',
			'name' => $this->getName().'[]',
			'value' => $this->Value(),			
			'class' => $this->extraClass(),
			'id' => $this->ID(),
			'disabled' => $this->isDisabled(),
		);

		return array_merge($attrs, $this->attributes);
	}

	/**
	 * Sets the items assigned to this field as an SS_List of File objects.
	 * Calling setItems will also update the value of this field, as well as 
	 * updating the internal list of File items.
	 * 
	 * @param SS_List $items
	 * @return UploadField self reference
	 */	
	public function setItems(SS_List $items) {
		return $this->setValue(null, $items);
	}

	/**
	 * Retrieves the current list of files
	 * 
	 * @return SS_List
	 */
	public function getItems() {
		return $this->items ? $this->items : new ArrayList();
	}
	
	protected function extractUploadedFileData($files){
		
		$keys = array_keys($files);
		$arrFiles = array();
		$i = 0;
		while($i < count($files['name'])){
			foreach($keys as $key){
				$arrFiles[$i][$key] = $files[$key][$i];
			}
			$i++;
	
		}
		return $arrFiles;
		
	}
	/**
	 * Force a record to be used as "Parent" for uploaded Files (eg a Page with a has_one to File)
	 * @param DataObject $record
	 */
	public function setRecord($record) {
		$this->record = $record;
		return $this;
	}
	/**
	 * Get the record to use as "Parent" for uploaded Files (eg a Page with a has_one to File) If none is set, it will
	 * use Form->getRecord() or Form->Controller()->data()
	 * 
	 * @return DataObject
	 */
	public function getRecord() {
		if (!$this->record && $this->form) {
			if (($record = $this->form->getRecord()) && ($record instanceof DataObject)) {
				$this->record = $record;
			} elseif (($controller = $this->form->Controller())
				&& $controller->hasMethod('data') 
				&& ($record = $controller->data())
				&& ($record instanceof DataObject)
			) {
				$this->record = $record;
			}
		}
		return $this->record;
	}
	
	/**
	 * Gets the foreign class that needs to be created, or 'File' as default if there
	 * is no relationship, or it cannot be determined.
	 *
	 * @param $default Default value to return if no value could be calculated
	 * @return string Foreign class name.
	 */
	public function getRelationAutosetClass($default = 'File') {
		
		// Don't autodetermine relation if no relationship between parent record
		if(!$this->relationAutoSetting) return $default;
					
		// Check record and name
		$name = $this->getName();
		$record = $this->getRecord();
		if(empty($name) || empty($record)) {
			return $default;
		} else {
			$class = $record->getRelationClass($name);
			return empty($class) ? $default : $class;
		}
	}
	
	/**
	 * Loads the temporary file data into a File object
	 * 
	 * @param array $tmpFile Temporary file data
	 * @param string $error Error message
	 * @return File File object, or null if error
	 */
	protected function saveTemporaryFile($tmpFile, &$error = null) {

		// Determine container object
		$error = null;
		$fileObject = null;
		
		if (empty($tmpFile)) {
			$error = _t('UploadField.FIELDNOTSET', 'File information not found');
			return null;
		}
		
		if($tmpFile['error']) {
			$error = $tmpFile['error'];
			return null;
		}
		
		// Search for relations that can hold the uploaded files, but don't fallback
		// to default if there is no automatic relation
		if ($relationClass = $this->getRelationAutosetClass(null)) {
			// Create new object explicitly. Otherwise rely on Upload::load to choose the class.
			$fileObject = Object::create($relationClass);
		}

		// Get the uploaded file into a new file object.
		try {
			$this->upload->loadIntoFile($tmpFile, $fileObject, $this->getFolderName());
		} catch (Exception $e) {
			// we shouldn't get an error here, but just in case
			$error = $e->getMessage();
			return null;
		}

		// Check if upload field has an error
		if ($this->upload->isError()) {
			$error = implode(' ' . PHP_EOL, $this->upload->getErrors());
			return null;
		}
		
		// return file
		return $this->upload->getFile();
	}
	
	public function validate($validator) {
		
		$name = $this->getName();
		
		$files = $this->extractUploadedFileData($_FILES[$name]);
		
		// Revalidate each file against nested validator
		$this->upload->clearErrors();
		foreach($files as $file) {
			$this->upload->validate($file);
		}
		
		// Check all errors
		if($errors = $this->upload->getErrors()) {
			foreach($errors as $error) {
				$validator->validationError($name, $error, "validation");
			}
			return false;
		}
		
		return true;
	}
	
	public function saveInto(DataObjectInterface $record) {
		
		$this->setRecord($record);
		
		$fieldname = $this->getName();
		
		if(!isset($_FILES[$fieldname])) return false;
		
		$files = $_FILES[$fieldname];
		// Save the temporary file into a File object
		$uploadedFiles = $this->extractUploadedFileData($files);
		
		$relation = $record->hasMethod($fieldname) ? $record->$fieldname() : null;
		$error = '';
		$fileIDs = array();
		foreach ($uploadedFiles as $uploadedFile){
			
			$file = $this->saveTemporaryFile($uploadedFile, $error);
			if(empty($file)) {
				return false;
			}
			$fileIDs[] = $file->ID;
			
		}

		//save the file to relation list on $record
		if($relation && ($relation instanceof RelationList || $relation instanceof UnsavedRelationList)) {
			$relation->setByIDList($fileIDs);
		}
		elseif($record->has_one($fieldname)) {
			$record->{"{$fieldname}ID"} = $fileIDs[0];
		}
		
		return $this;
		
	}
	
}
