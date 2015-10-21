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

require_once('phpseclib/Net/SFTP.php');

class Bazaarvoice_Connector_Helper_Sftp extends Net_SFTP 
{
    
    /**
     * Downloads a file from the SFTP server.
     *
     * Returns a string containing the contents of $remoteFile if $localFile is left undefined or a boolean false if
     * the operation was unsuccessful.  If $localFile is defined, returns true or false depending on the success of the
     * operation
     *
     * @param String $remoteFile
     * @param optional String $localFile
     * @return Mixed
     * @access public
     */
    function get($remoteFile, $localFile = false)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            $this->sftp_errors[] = "No bitmap and Mask Login";
            return false;
        }

        $remoteFile = $this->_realpath($remoteFile);
        if ($remoteFile === false) {
            $this->sftp_errors[] = "Remote file not found";
            return false;
        }

        $packet = pack('Na*N2', strlen($remoteFile), $remoteFile, NET_SFTP_OPEN_READ, 0);
        if (!$this->_send_sftp_packet(NET_SFTP_OPEN, $packet)) {
            $this->sftp_errors[] = "Cannot send SFTP Open";
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_HANDLE:
                $handle = substr($response, 4);
                break;
            case NET_SFTP_STATUS: // presumably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        $packet = pack('Na*', strlen($handle), $handle);
        if (!$this->_send_sftp_packet(NET_SFTP_FSTAT, $packet)) {
            $this->sftp_errors[] = "Cannot send FSTAT";
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_ATTRS:
                $attrs = $this->_parseAttributes($response);
                break;
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_ATTRS or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        if ($localFile !== false) {
            $filePointer = fopen($localFile, 'wb');
            if (!$filePointer) {
                $this->sftp_errors[] = "Cannot write local file";
                return false;
            }
        } else {
            $content = '';
        }

        $log = 0;
        $read = 0;
        while ($read < $attrs['size']) {
            $packet = pack('Na*N3', strlen($handle), $handle, 0, $read, 1 << 20);
            if (!$this->_send_sftp_packet(NET_SFTP_READ, $packet)) {
                $this->sftp_errors[] = "Cannot send read packet";
                return false;
            }

            $response = $this->_get_sftp_packet();
            switch ($this->packet_type) {
                case NET_SFTP_DATA:
                    $temp = substr($response, 4);
                    $read+= strlen($temp);
                    if ($localFile === false) {
                        $content.= $temp;
                    } else {
                        fputs($filePointer, $temp);
                    }
                    break;
                case NET_SFTP_STATUS:
                    extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                    $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                    break 2;
                default:
                    user_error('Expected SSH_FXP_DATA or SSH_FXP_STATUS', E_USER_NOTICE);
                    return false;
            }
            if ($log++ % 50 == 0) {
                $bytesIn = $this->formatBytes($read);
                $bytesTotal = $this->formatBytes($attrs['size']);
                Mage::log("     BVSFTP - Retrieved $bytesIn of $bytesTotal.", Zend_Log::INFO, Bazaarvoice_Connector_Helper_Data::LOG_FILE);
            }
        }

        if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        if (isset($content)) {
            return $content;
        }

        fclose($filePointer);
        return true;
    }
    
    public function formatBytes($bytes, $precision = 2) 
    { 
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    
        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 
    
        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
    
        return round($bytes, $precision) . ' ' . $units[$pow]; 
    } 
    
}