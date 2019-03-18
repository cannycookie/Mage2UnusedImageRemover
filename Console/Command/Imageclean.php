<?php
/**
 * Inspired by jeroenvermeulen Clean Images.
 *
 * End-User License Agreement (EULA) of EkoUK/ImageCleaner
 * 
 * License Grant
 * 
 * EKO UK LTD hereby grants you a personal, non-transferable, non-exclusive licence to use the EkoUK/ImageCleaner software on your devices in accordance with the terms of this EULA agreement.
 * 
 * You are permitted to load the EkoUK/ImageCleaner software (for example a PC, laptop, mobile or tablet) under your control. You are responsible for ensuring your device meets the minimum requirements of the EkoUK/ImageCleaner software.
 * 
 * You are not permitted to:
 * 
 * - Edit, alter, modify, adapt, translate or otherwise change the whole or any part of the Software nor permit the whole or any part of the Software to be combined with or become incorporated in any other software, nor decompile, disassemble or reverse engineer the Software or attempt to do any such things
 * - Reproduce, copy, distribute, resell or otherwise use the Software for any commercial purpose
 * - Allow any third party to use the Software on behalf of or for the benefit of any third party
 * - Use the Software in any way which breaches any applicable local, national or international law
 * - Use the Software for any purpose that EKO UK LTD considers is a breach of this EULA agreement
 * 
 * Full License may be found here: https://www.ekouk.com/software-end-user-licence-agreement/
 */

namespace EkoUK\ImageCleaner\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;

class Imageclean extends Command
{

    const DELETE_MODE = "Delete Mode";
    const ALLOWED_FILE_TYPES = ['jpg','jpeg','png'];

    protected $io;
    protected $file;
    protected $directoryList;
    protected $resourceConnection;
    protected $imagesPath;
    protected $deleteMode;
    protected $db;

    public function __construct(
        \Magento\Framework\Filesystem\Driver\File $file,
        \Magento\Framework\Filesystem\Io\File $io,
        DirectoryList $directoryList,
        ResourceConnection $resourceConnection
    ){
        $this->io = $io;
        $this->file = $file;
        $this->directoryList = $directoryList;
        $this->resourceConnection = $resourceConnection;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->deleteMode = $input->getOption(self::DELETE_MODE);
        $this->imagesPath = $this->getCatalogDir();
        $this->db         = $this->resourceConnection->getConnection();

        $output->writeln("Checking Files In Directory: ".$this->imagesPath);
        $localImages = $this->getImagesFromDirectoryRecursive($this->imagesPath);
        $output->writeln("Found ".count($localImages)." local image files");

        $dbImages = $this->getImagesFromDatabase();

        $deleteList = $this->createListToDelete($localImages,$dbImages);

        if($this->deleteMode){
            $output->writeln("Deleting Files");
            $this->deleteImages($deleteList);
            $output->writeln("All Done");

        } else {
            $output->writeln("Test Mode Only - Nothing deleted");
        }


    }


    private function getImagesFromDatabase()
    {
        $galleryImages = $this->getGalleryImages();
        $productImages = $this->getProductImages();
        $flatCatImages = $this->getFlatCatalogImages();

        $databaseImages = array_unique(array_merge($galleryImages,$productImages,$flatCatImages));

        return $this->validateDbImages($databaseImages);


    }

    private function getFlatCatalogImages()
    {
        $tables = $this->getFlatCatalogTables();
        $attributes = $this->getImageAttributeCodes();
        $dbImages = [];

        foreach( $tables as $table ) {
            foreach( $attributes as $column ) {
                if ( $this->fieldExists($table,$column) ) {
                    $fieldImages = $this->getColumnFromTable($column,$table);
                    $dbImages = array_merge( $dbImages, $fieldImages );
                }
            }
        }
        return $dbImages;
    }

    private function getFlatCatalogTables()
    {
        return $this->db->getTables('catalog_product_flat_%%');
    }

    private function getColumnFromTable($column,$table)
    {
        $sql = "SELECT $column FROM $table";
        return $this->db->fetchCol($sql);
    }

    private function getGalleryImages()
    {
        $table = $this->db->getTableName('catalog_product_entity_media_gallery');
        $sql = "SELECT value FROM $table";
        return $this->db->fetchCol($sql);
    }

    private function getProductImages()
    {
        $ids = $this->getImageAttributeIds();
        $imageAttributeIds = implode(', ',$ids);
        $table = $this->db->getTableName('catalog_product_entity_varchar');
        $sql = "SELECT value FROM $table WHERE attribute_id in ($imageAttributeIds)";
        return $this->db->fetchCol($sql);
    }

    private function getImageAttributeIds()
    {
        $table1 = $this->db->getTableName('eav_attribute');
        $table2 = $this->db->getTableName('eav_entity_type');
        $sql = "SELECT attribute_id FROM $table1 INNER JOIN $table2 USING (entity_type_id) WHERE entity_type_code = 'catalog_product' AND frontend_input like ('media_image')";
        return $this->db->fetchCol($sql);

    }

    private function getImageAttributeCodes()
    {
        $table1 = $this->db->getTableName('eav_attribute');
        $table2 = $this->db->getTableName('eav_entity_type');
        $sql = "SELECT attribute_code FROM $table1 INNER JOIN $table2 USING (entity_type_id) WHERE entity_type_code = 'catalog_product' AND frontend_input like ('media_image')";
        return $this->db->fetchCol($sql);
    }


    protected function fieldExists( $table, $column )
    {
        return $this->db->tableColumnExists($table,$column);
    }


    private function getImagesFromDirectoryRecursive($directory,&$results = [])
    {
        if ($directoryContents = $this->file->readDirectory($directory)) {
            foreach ($directoryContents as $key => $path) {
                if(!is_dir($path)){
                    $match=false;
                    foreach (self::ALLOWED_FILE_TYPES as $ext){
                        if($this->endsWith(strtolower($path),$ext)){
                            $results[]=$path;
                        }
                    }
                    if(!$match) unset($directoryContents[$key]);
                } else if($path != "." && $path != ".." ){
                    $this->getImagesFromDirectoryRecursive($path,$results);
                }

            }
        }
        return $results;
    }

    protected function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }
        return (substr($haystack, -$length) === $needle);
    }

    protected function getCatalogDir()
    {
        return $this->directoryList->getPath('media').'/catalog/product/';
    }

    private function createListToDelete($localImages,$dbImages){

        $dbImageFlip = array_flip( $dbImages );
        $deleteList = array();
        $deleteSize = 0;
        foreach ($localImages as $file){
            if ( !isset( $dbImageFlip[$file] ) ) {
                if ( is_writable( $file ) ) {
                    $deleteList[] = $file;
                    $deleteSize += filesize( $file ) / 1024 / 1024; // Add in Mb
                } else {
                    printf( "Warning: File '%s' is not writable, skipping.\n", $file );
                }
            }
        }
        printf( "Found %d image files to be deleted, using %d Mb\n", count( $deleteList ), $deleteSize );
        return $deleteList;
    }


    private function validateDbImages($dbImages)
    {
        //We only want a list of files that are present in DB & Filesystem
        $countInvalid = 0;
        $keys = array_keys($dbImages);
        foreach ( $keys as $key ) {
            $dbImages[$key] = trim( $dbImages[$key] );
            if ( empty( $dbImages[$key] ) ) {
                unset( $dbImages[$key] );
            } else {
                //Test if file exists
                $fullPath = realpath( $this->imagesPath . $dbImages[$key] );
                if ( false === $fullPath ) {
                    unset( $dbImages[$key] );
                    $countInvalid++;
                }
                elseif ( 0 !== strpos( $fullPath, $this->imagesPath ) ) {
                    printf( "Warning: Image path outside image root used: '%s'.\n", $fullPath );
                    unset( $dbImages[$key] );
                    $countInvalid++;
                } else {
                    $dbImages[$key] = $fullPath;
                }
            }
        }
        $dbImages = array_unique( $dbImages );
        printf( "Found %d invalid images in database.\n", $countInvalid );
        printf( "Found %d valid images in database.\n", count( $dbImages ) );
        return $dbImages;
    }

    private function deleteImages($deleteList){
        foreach( $deleteList as $deleteFile ) {
            unlink( $deleteFile );
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("ekouk:imageclean");
        $this->setDescription("Removes unused images from pub/media/catalog");
        $this->setDefinition([
            new InputOption(self::DELETE_MODE, "-d", InputOption::VALUE_NONE, "Delete Mode")
        ]);
        parent::configure();
    }
}
