<?php

/*
 */

namespace DSLive\Models;

/**
 * Description of File
 *
 * @author topman
 */
abstract class File extends Model {

    private $extensions;
    private $badExtensions;
    private $maxSize;

    /**
     * The name of the property to use as the name of the file when saving to filesystem
     * @var string
     */
    private $altNameProperty;

    /**
     * @DBS\String (size=50, nullable=true)
     */
    protected $mime;

    abstract public function __construct();

    /**
     * Adds a file extension type to a file property
     * @param string $property
     * @param string $ext
     * @return \DSLive\Models\File
     * @throws \Exception
     */
    final public function addExtension($property, $ext) {
        if (!property_exists($this, $property)) {
            throw new \Exception('Add File Extension Error: Property "' . $property . '" does not exists');
        }

        $this->extensions[$property][] = $ext;
        return $this;
    }

    /**
     * Sets the extensions for the given property. If any extensions existed for the property,
     * they will be overriden.
     * @param string $property
     * @param array $extensions
     * @return \DSLive\Models\File
     * @throws \Exception
     */
    final public function setExtensions($property, array $extensions) {
        if (!property_exists($this, $property)) {
            throw new \Exception('File Add Extension Error: Property "' . $property . '" does not exists');
        }

        $this->extensions[$property] = $extensions;
        return $this;
    }

    /**
     * Sets the name of the property to use as the name of the file when saving to filesystem
     * @param string $altNameProperty
     * @return \DSLive\Models\File
     */
    final public function setAltNameProperty($altNameProperty) {
        $this->altNameProperty = $altNameProperty;
        return $this;
    }

    /**
     * Adds a bad file extension type to a file property
     * @param string $property
     * @param string $ext
     * @return \DSLive\Models\File
     * @throws \Exception
     */
    final public function addBadExtension($property, $ext) {
        if (!property_exists($this, $property)) {
            throw new \Exception('Add Bad File Extension Error: Property "' . $property . '" does not exists');
        }

        $this->badExtensions[$property][] = $ext;
        return $this;
    }

    /**
     * Sets the bad extensions for the given property. If any bad extensions existed for the property,
     * they will be overriden.
     * @param string $property
     * @param array $extensions
     * @return \DSLive\Models\File
     * @throws \Exception
     */
    final public function setBadExtensions($property, array $extensions) {
        if (!property_exists($this, $property)) {
            throw new \Exception('Add Bad File Extension Error: Property "' . $property . '" does not exists');
        }

        $this->badExtensions[$property] = $extensions;
        return $this;
    }

    /**
     * Sets the maximum size of the file to upload
     * @param int|string $size
     * @return \DSLive\Models\File
     */
    final public function setMaxSize($size) {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * Fetches the byte value of the max filesize
     * If none is specified, uses the php_ini upload_max_filesize
     *
     * @return int
     */
    final public function getMaxSize() {
        if ($this->maxSize === null) {
            $this->maxSize = ini_get('upload_max_filesize');
        }

        return $this->parseSize($this->maxSize);
    }

    /**
     * Uploads files to the server
     * @param array|\Object $files
     * @return boolean
     * @throws \Exception
     */
    final public function uploadFiles($files) {
        if (is_object($files) && get_class($files) === 'Object') {
            $files = $files->toArray(true);
        }
        else if (is_object($files) || !is_array($files)) {
            throw new \Exception('Param $files must be either an object of type \Object or an array');
        }

        foreach ($files as $ppt => $info) {
            if (empty($info['name']))
                continue;

            if (!property_exists($this, $ppt))
                continue;

            $extension = $this->fileIsOk($ppt, $info);
            if (!$extension)
                return false;

            $dir = DATA . \Util::_toCamel($this->getTableName()) . DIRECTORY_SEPARATOR . $extension;
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    throw new \Exception('Permission denied to directory "' . DATA . '"');
                }
            }

            $name = ($this->altNameProperty !== null) ? preg_replace('/[^A-Z0-9._-]/i', '_', basename($this->{$this->altNameProperty})) . '.' . $extension :
                    time() . '_' . preg_replace('/[^A-Z0-9._-]/i', '_', basename($info['name']));
            $savePath = $dir . DIRECTORY_SEPARATOR . $name;

            if (move_uploaded_file($info['tmpName'], $savePath)) {
                $this->unlink($ppt);
                $this->$ppt = $savePath;
            }
            else {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks the info against property settings
     * @param string $property
     * @param array $info
     * @return boolean
     */
    final public function fileIsOk($property, array $info) {
        if ($info['error'] !== UPLOAD_ERR_OK)
            return false;

        if (!$this->sizeIsOk($info['size']))
            return false;
        $this->mime = $info['type'];
        $info = pathinfo($info['name']);
        return $this->extensionIsOk($property, $info['extension']);
    }

    /**
     * Checks if the given extension is allowed for the given property
     * @param string $property
     * @param string $extension
     * @return boolean
     */
    final public function extensionIsOk($property, $extension) {
        $extension = strtolower($extension);
        if (isset($this->extensions [$property]) && !in_array($extension, $this->extensions[$property]))
            return false;
        if (isset($this->badExtensions [$property]) && in_array($extension, $this->badExtensions[$property]))
            return false;

        return $extension;
    }

    /**
     * Checks if the given size is not bigger than the expected size for the property
     * @param int|string $size
     * @return boolean
     */
    final public function sizeIsOk($size) {
        if ($size > $this->getMaxSize()) {
            return false;
        }

        return true;
    }

    /**
     * Converts string sizes (kb,mb) to int size (bytes)
     * @param string|int $size
     * @return int
     * @throws \Exception
     */
    final public function parseSize($size) {
        if (is_int($size))
            return $size;

        if (!is_string($size)) {
            throw new \Exception('File sizes must either be an integer or a string');
        }

        if (strtolower(substr($size, strlen($size) - 1)) === 'k' || strtolower(substr($size, strlen($size) - 2)) === 'kb') {
            return (int) $size * 1000;
        }
        elseif (strtolower(substr($size, strlen($size) - 1)) === 'm' || strtolower(substr($size, strlen($size) - 2)) === 'mb') {
            return (int) $size * 1000000;
        }
    }

    /**
     * Deletes the file in the given property
     * @param string $property
     * @return boolean
     */
    final public function unlink($property) {
        if (property_exists($this, $property) && is_string($this->$property) &&
                is_file($this->$property))
            return unlink($this->$property);

        return true;
    }

    public function getMime() {
        return $this->mime;
    }

    public function setMime($mime) {
        $this->mime = $mime;
        return $this;
    }

}
