<?php
/**
 * @copyright 2010-2015 JTL-Software GmbH
 * @package jtl\Connector\Model
 * @subpackage Product
 */

namespace jtl\Connector\Model;

use JMS\Serializer\Annotation as Serializer;

/**
 * @access public
 * @package jtl\Connector\Model
 * @subpackage Product
 * @Serializer\AccessType("public_method")
 */
class FileUploadI18n extends DataModel
{
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("description")
     * @Serializer\Accessor(getter="getDescription",setter="setDescription")
     */
    protected $description = '';
    
    /**
     * @var integer
     * @Serializer\Type("integer")
     * @Serializer\SerializedName("fileUploadId")
     * @Serializer\Accessor(getter="getFileUploadId",setter="setFileUploadId")
     */
    protected $fileUploadId = 0;
    
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("languageISO")
     * @Serializer\Accessor(getter="getLanguageISO",setter="setLanguageISO")
     */
    protected $languageISO = '';
    
    /**
     * @var string
     * @Serializer\Type("string")
     * @Serializer\SerializedName("name")
     * @Serializer\Accessor(getter="getName",setter="setName")
     */
    protected $name = '';
    
    
    /**
     * @param string $description
     * @return FileUploadI18n
     */
    public function setDescription(string $description): FileUploadI18n
    {
        $this->description = $description;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
    
    /**
     * @param integer $fileUploadId
     * @return FileUploadI18n
     */
    public function setFileUploadId(int $fileUploadId): FileUploadI18n
    {
        $this->fileUploadId = $fileUploadId;
        
        return $this;
    }
    
    /**
     * @return int
     */
    public function getFileUploadId(): int
    {
        return $this->fileUploadId;
    }
    
    /**
     * @param string $languageISO
     * @return FileUploadI18n
     */
    public function setLanguageISO(string $languageISO): FileUploadI18n
    {
        $this->languageISO = $languageISO;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getLanguageISO(): string
    {
        return $this->languageISO;
    }
    
    /**
     * @param string $name
     * @return FileUploadI18n
     */
    public function setName(string $name): FileUploadI18n
    {
        $this->name = $name;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
