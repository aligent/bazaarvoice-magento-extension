<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to commercial source code license 
 * of StoreFront Consulting, Inc.
 *
 * @copyright	(C)Copyright 2015 StoreFront Consulting, Inc (http://www.StoreFrontConsulting.com/)
 * @package		Bazaarvoice_Connector
 * @author		Dennis Rogers <dennis@storefrontconsulting.com>
 */

// Includes
require_once('phpseclib/Net/SFTP.php');

class Bazaarvoice_Connector_Helper_SftpConnection extends Mage_Core_Helper_Abstract
{
    /**
     * Constants
     *
     * Hardcode timeout value
     */
    const SFTP_TIMEOUT = 20;

    /**
     * Connection handle
     */
    private $_oConnection = null;

    /**
     * Connect
     * @return boolean
     */
    public function connect($host, $port, $user, $pass)
    {
        try {
            // Close
            if (isset($this->_oConnection)) {
                $this->close();
            }

            // Get config values
            $server = $host;
            $server = ($server ? trim($server) : '');
            $port = $port;
            $port = ($port ? trim($port) : '');
            $username = $user;
            $username = ($username ? trim($username) : '');
            $password = $pass;
            $password = ($password ? trim($password) : '');
            
            // Log
            Mage::log('    BVSFTP - Host: ' . $server, Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            Mage::log('    BVSFTP - Username: ' . $username, Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
//            Mage::log('Password: ' . $password, Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);

            // Check credentials
            if (!strlen($server)) {
                Mage::throwException('Invalid host: ' . $server);
            }
            if (!strlen($port)) {
                Mage::throwException('Invalid port: ' . $port);
            }
            if (!strlen($username)) {
                Mage::throwException('Invalid user: ' . $username);
            }
            if (!strlen($password)) {
                Mage::throwException('Invalid password: ' . $password);
            }

            // -- Open connection
            $this->_oConnection = new Bazaarvoice_Connector_Helper_Sftp($server, $port, self::SFTP_TIMEOUT);
            if (!$this->_oConnection->login($username, $password)) {
                Mage::throwException(sprintf(__('Unable to open SFTP connection as %s@%s', $username, $server)));
            }

            return true;
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            Mage::getSingleton('adminhtml/session')->addError('Could not connect to SFTP server');
        }

        return false;
    }

    /**
     * Close
     * @return boolean
     */
    public function close()
    {
        try {
            // Close connection
            if (isset($this->_oConnection)) {
                $res = $this->_oConnection->disconnect();
                unset($this->_oConnection);

                return $res;
            }
            else {
                Mage::throwException('Connection not open!');
            }
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * Is connected
     * @return boolean
     */
    public function isConnected()
    {
        return (isset($this->_oConnection));
    }

    /**
     * Change directory
     * @param string directory
     * @return boolean
     */
    public function changeDir($dir)
    {
        try {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->chdir($dir);
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * Make directory
     * @param string directory
     * @return boolean
     */
    public function makeDir($dir)
    {
        try {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->mkdir($dir);
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * List files
     * @param string directory
     * @return array
     */
    public function listFiles($dir = '.')
    {
        try {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            return $this->_oConnection->nlist($dir);
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }
    
    /**
     * Transfer file
     * @param string Local file path
     * @return boolean
     */
    public function getFile($remoteFilePath, $localFilePath)
    {
        Mage::log("    BVSFTP - Get remote file $remoteFilePath => $localFilePath", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        try {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }

            // Get filename
            $filename = basename($remoteFilePath);
    
            if(dirname($remoteFilePath))
                $this->changeDir(dirname($remoteFilePath));

            // Transfer
            $success = $this->_oConnection->get($filename, $localFilePath);

            // Check success and log errors
            if (!$success) {
                Mage::log('     BVSFTP - Error: ' .
                $this->_oConnection->getLastSFTPError(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }

            // Return success
            return $success;
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }    

    /**
     * Transfer file
     * @param string Local file path
     * @return boolean
     */
    public function putFile($localFilePath, $remoteFile = '')
    {
        if(!$remoteFile)
            $remoteFile = basename($localFilePath);
            
        Mage::log("    BVSFTP - Put local file $localFilePath as $remoteFile", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        try {
            // Close connection
            if (!$this->isConnected()) {
                return false;
            }
    
            if(dirname($remoteFile))
                $this->changeDir(dirname($remoteFile));

            // Transfer
            $success = $this->_oConnection->put($remoteFile, $localFilePath, NET_SFTP_LOCAL_FILE);

            // Check success and log errors
            if (!$success) {
                Mage::log('     BVSFTP - '.$this->_oConnection->getLastSFTPError(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }

            // Return success
            return $success;
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    }

    /**
     * Transfer file and delete when successful as one atomic operation
     * @param string Local file path
     * @return boolean
     */
    public function putAndDeleteFile($localFilePath, $remoteFile = '')
    {
        try {
            $success = $this->putFile($localFilePath, $remoteFile);
            if ($success) {
                $io = new Varien_Io_File();
                $io->rm($localFilePath);
            }

            return $success;
        }
        catch (Exception $e) {
            // Log
            Mage::logException($e);
            Mage::log('     BVSFTP - '.$e->getMessage(), Zend_Log::ERR, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
        }

        return false;
    } 
}
