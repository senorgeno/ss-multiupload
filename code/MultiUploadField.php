<?php


/**
 * A simple front end multi upload field for SS 3.1.X
 *
 * @author Gene
 */
class MultiUploadField extends UploadField {

	protected $ufConfig = array(
		/**
		 * Automatically upload the file once selected
		 * 
		 * @var boolean
		 */
		'autoUpload' => false,
		/**
		 * Restriction on number of files that may be set for this field. Set to null to allow
		 * unlimited. If record has a has_one and allowedMaxFileNumber is null, it will be set to 1.
		 * The resulting value will be set to maxNumberOfFiles
		 * 
		 * @var integer
		 */
		'allowedMaxFileNumber' => null,
	    
		'allowedMaxUpload' => null

	);
	
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
		
		$properties = array_merge($properties, array(
			'MaxFileSize' => $this->getValidator()->getAllowedMaxFileSize()
		));
		
		$obj = ($properties) ? $this->customise($properties) : $this;

		return $obj->renderWith($this->getTemplates());
		
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
	
	public function setAllowedMaxUpload($allowedMaxUpload) {
		$number=substr($allowedMaxUpload,0,-2);
		switch(strtoupper(substr($allowedMaxUpload,-2))){
			case "KB":
				$maxUpload = $allowedMaxUpload*1024;
				break;
			case "MB":
				$maxUpload = $allowedMaxUpload*pow(1024,2);
				break;
			case "GB":
				$maxUpload = $allowedMaxUpload*pow(1024,3);
				break;
			case "TB":
				$maxUpload = $allowedMaxUpload*pow(1024,4);
				break;
			case "PB":
				$maxUpload = $allowedMaxUpload*pow(1024,5);
				break;
			default:
				$maxUpload = $allowedMaxUpload;
		}
		
		return $this->setConfig('allowedMaxUpload', $maxUpload);
	}
	
	public function getAllowedMaxUpload() {
		$allowedMaxUpload = $this->getConfig('allowedMaxUpload');
		
		// if there is a has_one relation with that name on the record and 
		// allowedMaxFileNumber has not been set, it's wanted to be 1
		if(empty($allowedMaxUpload)) {
			return null;
		} 
			
		return $allowedMaxUpload;

	}
	
	/**
	 * Set the field value.
	 * 
	 * @param mixed $value
	 * @return FormField Self reference
	 */
	public function setValue($value, $record = null) {
		$this->value = $value;
		return $this;
	}
	
	public function validate($validator) {
		
		$name = $this->getName();
		
		$files = $this->extractUploadedFileData($_FILES[$name]);

		// Check max number of files 
		$maxFiles = $this->getAllowedMaxFileNumber();
		if($maxFiles && (count($files) > $maxFiles)) {
			$validator->validationError(
				$name,
				_t(
					'MultiUploadField.MAXNUMBEROFFILES', 
					'Max number of {count} file(s) exceeded.',
					array('count' => $maxFiles)
				),
				"validation"
			);
			return false;
		}
		
		// Revalidate each file against nested validator
		$this->upload->clearErrors();
		$totalFileSize = 0;
		foreach($files as $file) {
			$totalFileSize += $file['size'];
			$this->upload->validate($file);
		}
		
		// Check all errors from upload validator
		if($errors = $this->upload->getErrors()) {
			foreach($errors as $error) {
				$validator->validationError($name, $error, "validation");
			}
			return false;
		}
		$maxTotalUpload = $this->getAllowedMaxUpload();
		//check total max upload	
		if($totalFileSize > $maxTotalUpload){

			$validator->validationError(
				$name,
				_t(
					'MultiUploadField.MAXTOTALUPLOAD', 
					'Max upload of {amount} has been exceeded.',
					array('amount' => $maxTotalUpload)
				),
				"validation"
			);
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
