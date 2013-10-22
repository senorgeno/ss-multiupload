silverstripe-multiupload
========================

 A simple frontend multi-upload field for SS 3.1.X

## Basic setup ##

MultiUploadField::create('Files', 'Upload')
			  ->setFolderName('Uploads/sample')
			  ->setAllowedMaxFileNumber(5)
			  ->setAllowedMaxUpload('1MB')
