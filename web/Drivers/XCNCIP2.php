<?php
/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
require_once 'Interface.php';
require_once 'sys/Proxy_Request.php';

/**
 * XC NCIP Toolkit (v2) ILS Driver
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class XCNCIP2 implements DriverInterface
{
    /**
     * Values loaded from XCNCIP2.ini.
     *
     * @var    array
     * @access private
     */
    private $_config;

    /**
     * Constructor
     *
     * @access public
     */
    function __construct()
    {
        // Load Configuration for this Module
        $this->_config = parse_ini_file(
            dirname(__FILE__) . '/../conf/XCNCIP2.ini', true
        );
    }

    /**
     * Send an NCIP request.
     *
     * @param string $xml XML request document
     *
     * @return object     SimpleXMLElement parsed from response
     * @access private
     */
    private function _sendRequest($xml)
    {
        // Make the NCIP request:
        $client = new Proxy_Request(null, array('useBrackets' => false));
        $client->setMethod(HTTP_REQUEST_METHOD_POST);
        $client->setURL($this->_config['Catalog']['url']);
        $client->addHeader('Content-type', 'application/xml; "charset=utf-8"');
        $client->setBody($xml);
        $result = $client->sendRequest();
        if (PEAR::isError($result)) {
            PEAR::raiseError($result);
        }
        // Process the NCIP response:
        $response = $client->getResponseBody();
        $result = @simplexml_load_string($response);
        if (is_a($result, 'SimpleXMLElement')) {
            $result->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
	        return $result;    
        } else {
            PEAR::raiseError(new PEAR_Error("Problem parsing XML".$response));
        }
    }

    /**
     * Given a chunk of the availability response, extract the values needed
     * by VuFind.
     *
     * @param array $current Current XCItemAvailability chunk.
     *
     * @return array
     * @access private
     */
    private function _getHoldingsForChunk($current)
    {
        static $number = 0;
        $result = array();
        if (empty($current)) {
           //sample chunk for untracked items
           return $this->_getSampleChunk();
        }
        
        $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');  
        //iterate over all items
       
        $items = $current->xpath('ns1:ItemInformation');
        foreach ($items as $item) {
            $item->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
            $status = $item->xpath(
                'ns1:ItemOptionalFields/ns1:CirculationStatus'
            );
            $status = empty($status) ? '' : (string)$status[0];
          
            // Pick out the permanent location (TODO: better smarts for dealing with
            // temporary locations and multi-level location names):
            //   $locationNodes = $current->xpath('ns1:HoldingsSet/ns1:Location');
            $location = '';
           
    /*
     foreach ($locationNodes as $curLoc) {
                $type = $curLoc->xpath('ns1:LocationType');
                if ((string)$type[0] == 'Permanent') {
                    $tmp = $curLoc->xpath(
                        'ns1:LocationName/ns1:LocationNameInstance/ns1:LocationNameValue'
                    );
                    $location = (string)$tmp[0];
                }
            }
    */
            
            $id = $item->xpath('ns1:ItemId/ns1:ItemIdentifierValue');
            // Get both holdings and item level call numbers; we'll pick the most
            // specific available value below.
            $holdCallNo = $current->xpath('ns1:HoldingsSet/ns1:CallNumber');
            $holdCallNo = (string)$holdCallNo[0];
            $itemCallNo = $current->xpath(
                'ns1:ItemOptionalFields/ns1:ItemDescription/ns1:CallNumber'
            );
            $itemCallNo = (string)$itemCallNo[0];
            
            // Build return array:
            $result[] = array(
                'item' => empty($id) ? '' : (string)$id[0],
                'availability' => ($status === 'Available On Shelf'),
                'status' => $status,
                'location' => $location,
                'reserve' => 'N',       // not supported
                'callnumber' => empty($itemCallNo) ? $holdCallNo : $itemCallNo,
                'duedate' => '',        // not supported
                'number' => $number++,
                // XC NCIP does not support barcode, but we need a placeholder here
                // to display anything on the record screen:
                'barcode' => 'placeholder' . $number
            );
            }
        return $result;
    }
    
    private function _getSampleChunk() {
        static $number = 0;
        $result = array();
        $result[] =  array(
            'item' => 'id',
            'availability' => false,
            'status' => 'X',
            'location' => 'X',
            'reserve' => 'N',       // not supported
            'callnumber' => 'A'.$number,
            'duedate' => '12.12.12',        // not supported
            'number' => ++$number,
            'barcode' => 'placeholder' . $number
             );
       return $result;
    }
    
    

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *$xml
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber; on
     * failure, a PEAR_Error.
     * @access public
     */
    public function getStatus($id)
    {
        // For now, we'll just use getHolding, since getStatus should return a
        // subset of the same fields, and the extra values will be ignored.
        return $this->getHolding($id);
    }

    /**
     * Build NCIP2 request XML for item status information.
     *
     * @param array  $idList     IDs to look up.
     * @param string $resumption Resumption token (null for first page of set).
     *
     * @return string            XML request
     * @access private
     */
    private function _getStatusRequest($idList, $resumption = null)
    {
  
    	$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
    		'<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" '.
    		'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_02.xsd">';
    
    	$xml .= '<ns1:LookupItemSet>';
    
    	foreach ($idList as $id) {
    		$xml .= '<ns1:BibliographicId><ns1:BibliographicRecordId>'.
                            '<ns1:BibliographicRecordIdentifier>'.
    			$id.
    			'</ns1:BibliographicRecordIdentifier><ns1:AgencyId>UIU</ns1:AgencyId>'.
                   		'</ns1:BibliographicRecordId>'.
           			 '</ns1:BibliographicId>';
    	}

	    $xml .= '<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Bibliographic Description</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Circulation Status</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Electronic Resource</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Hold Queue Length</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Item Description</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Item Use Restriction Type</ns1:ItemElementType>'.
		'<ns1:ItemElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/schemes/itemelementtype/itemelementtype.scm">Location</ns1:ItemElementType>'.
		'</ns1:LookupItemSet>'.
		'</ns1:NCIPMessage>';


        return $xml;
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @return mixed        An array of getStatus() return values on success,
     * a PEAR_Error object otherwise.
     * @access public
     */
    public function getStatuses($idList)
    {
        $status = array();
        $resumption = null;
       // do {
            $request = $this->_getStatusRequest($idList, $resumption);
            $response = $this->_sendRequest($request);
            $response->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');  
            $avail = $response->xpath(
                'ns1:LookupItemSetResponse/ns1:BibInformation'
            );
            // Build the array of statuses:
            foreach ($avail as $current) {
                // Get data on the current chunk of data:
                $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
                $holdingsSet = $current->xpath("ns1:HoldingsSet");
                $chunk = $this->_getHoldingsForChunk($holdingsSet[0]);
		


                // Each bibliographic ID has its own key in the $status array; make
                // sure we initialize new arrays when necessary and then add the
                // current chunk to the right place:
                $id = $current->xpath('ns1:BibliographicId/ns1:BibliographicRecordId/ns1:BibliographicRecordIdentifier');
     
                $id = (string)$id[0];
                if (empty($id)) {
                    $query = $current->xpath('ns1:Problem/ns1:ProblemValue');
                    $id = (string)$query[0];
                }
                
                //add id to each item
                for( $i = 0; $i != count($chunk); $i++) {
                    $chunk[$i]['id'] = $id;
                }
                $status[] = $chunk;

            }
            
            // Check for resumption token:
            $resumption = $response->xpath(
                'ns1:LookupItemSetResponse/ns1:NextItemToken'
            );
            $resumption = count($resumption) > 0 ? (string)$resumption[0] : null;

       // } while (!empty($resumption));
        return $status;
    }

    
    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id, $patron = false)
    {
        $request = $this->_getStatusRequest(array($id));
        $response = $this->_sendRequest($request);   
		$avail = $response->xpath(
            'ns1:LookupItemSetResponse/ns1:BibInformation/ns1:HoldingsSet'
        );
        $holdings = $this->_getHoldingsForChunk($avail[0]);
        for ($i = 0; $i < count($holdings); $i++) {
            $holdings[$i]['id']=$id;
        }
        return $holdings;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @return mixed     An array with the acquisitions data on success, PEAR_Error
     * on failure
     * @access public
     */
    public function getPurchaseHistory($id)
    {
        // TODO
        return array();
    }

    /**
     * Build the request XML to log in a user:
     *
     * @param string $username Username for login
     * @param string $password Password for login
     * @param string $extras   Extra elements to include in the request
     *
     * @return string          NCIP request XML
     * @access private
     */
    private function _getLookupUserRequest($username, $password, $extras = array())
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip" ' .
            'ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/' .
            'xsd/ncip_v2_0.xsd">' .
                '<ns1:LookupUser>' .
                    '<ns1:AuthenticationInput>' .
                        '<ns1:AuthenticationInputData>' .
                            htmlspecialchars($username) .
                        '</ns1:AuthenticationInputData>' .
                        '<ns1:AuthenticationDataFormatType>' .
                            'text' .
                        '</ns1:AuthenticationDataFormatType>' .
                        '<ns1:AuthenticationInputType>' .
                            'Username' .
                        '</ns1:AuthenticationInputType>' .
                    '</ns1:AuthenticationInput>' .
                    '<ns1:AuthenticationInput>' .
                        '<ns1:AuthenticationInputData>' .
                            htmlspecialchars($password) .
                        '</ns1:AuthenticationInputData>' .
                        '<ns1:AuthenticationDataFormatType>' .
                            'text' .
                        '</ns1:AuthenticationDataFormatType>' .
                        '<ns1:AuthenticationInputType>' .
                            'Password' .
                        '</ns1:AuthenticationInputType>' .
                    '</ns1:AuthenticationInput>' .
                    implode('', $extras) .
                '</ns1:LookupUser>' .
            '</ns1:NCIPMessage>';
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron username
     * @param string $password The patron password
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login, PEAR_Error on error.
     * @access public
     */
    public function patronLogin($username, $password)
    {
        $request = $this->_getLookupUserRequest($username, $password);
        $response = $this->_sendRequest($request);
        $id = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserId/ns1:UserIdentifierValue'
        );
        if (!empty($id)) {
            // Fill in basic patron details:
            $patron = array(
                'id' => (string)$id[0],
                'cat_username' => $username,
                'cat_password' => $password,
                'email' => null,
                'major' => null,
                'college' => null
            );

            // Look up additional details:
            $details = $this->getMyProfile($patron);
            if (!empty($details)) {
                $patron['firstname'] = $details['firstname'];
                $patron['lastname'] = $details['lastname'];
                return $patron;
            }
        }

        return null;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's transactions on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyTransactions($patron)
    {
        $extras = array('<ns1:LoanedItemsDesired/>');
        $request = $this->_getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->_sendRequest($request);
        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:LoanedItem');
	foreach ($list as $current) {
            $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');    
	    $due = $current->xpath('ns1:DateDue');
            $title = $current->xpath('ns1:Ext/ns1:BibliographicDescription/ns1:Title');
            // $id = $current->xpath('ns1:ItemIdentifierValue');
	    $pubyear = $current->xpath('ns1:Ext/ns1:BibliographicDescription/ns1:PublicationDate');

	    $retVal[] = array(
           //     'id' => (string)$id[0],
                'duedate' => (string)$due[0],
		'publication_year' => (string)$pubyear[0],
                'title' => (string)$title[0]
            );
        }

        return $retVal;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyFines($patron)
    {
	$extras = array('<ns1:UserFiscalAccountDesired/>');
        $request = $this->_getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->_sendRequest($request);

        $list = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserFiscalAccount/ns1:AccountDetails'
        );

        $fines = array();
        foreach ($list as $current) {
	    $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:Amount/ns1:MonetaryValue'
            );
            $amount = (string)$tmp[0];
            $tmp = $current->xpath('ns1:AccrualDate');
            $date = (string)$tmp[0];
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:FiscalTransactionType'
            );
            $desc = (string)$tmp[0];
	    
            $tmp = $current->xpath('ns1:FiscalTransactionInformation/ns1:ItemDetails/ns1:DateDue');
	    $due = (string)$tmp[0];
	    
            $tmp = $current->xpath('ns1:FiscalTransactionInformation/ns1:ItemDetails/ns1:DateCheckedOut');
            $checkout = (string)$tmp[0];

	   /* This is an item ID, not a bib ID, so it's not actually useful:
            $tmp = $current->xpath(
                'ns1:FiscalTransactionInformation/ns1:ItemDetails/' .
                'ns1:ItemId/ns1:ItemIdentifierValue'
            );
            $id = (string)$tmp[0];
             */
            $id = '';
            $fines[] = array(
                'amount' => $amount,
                'balance' => $amount,
                'checkout' => $checkout,
                'fine' => $desc,
                'duedate' => '',
                'createdate' => $date,
                'id' => $id,
		'duedate' => $due
            );
        }
        return $fines;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's holds on success, PEAR_Error
     * otherwise.
     * @access public
     */
    public function getMyHolds($patron)
    {
        $extras = array('<ns1:RequestedItemsDesired/>');
        $request = $this->_getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->_sendRequest($request);
	
        $retVal = array();
        $list = $response->xpath('ns1:LookupUserResponse/ns1:RequestedItem');
        foreach ($list as $current) {
            $current->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
	    $created = $current->xpath('ns1:DatePlaced');
            $expire = $current->xpath('ns1:PickupExpiryDate');
	    $title = $current->xpath('ns1:Ext/ns1:BibliographicDescription/ns1:Title');
	    $pos = $current->xpath('ns1:HoldQueuePosition');
            $retVal[] = array(
                'id' => false,
                'create' => (string)$created[0],
                'expire' => (string)$expire[0],
                'title' => (string)$title[0],
                'position' => (string)$pos[0]
            );
        }

        return $retVal;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @return mixed        Array of the patron's profile data on success,
     * PEAR_Error otherwise.
     * @access public
     */
    public function getMyProfile($patron)
    {
        $extras = array(
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'User Address Information' .
            '</ns1:UserElementType>',
            '<ns1:UserElementType ns1:Scheme="http://www.niso.org/ncip/v1_0/' .
                'schemes/userelementtype/userelementtype.scm">' .
                'Name Information' .
            '</ns1:UserElementType>'
        );
        $request = $this->_getLookupUserRequest(
            $patron['cat_username'], $patron['cat_password'], $extras
        );
        $response = $this->_sendRequest($request);

        $first = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:GivenName'
        );
        $last = $response->xpath(
            'ns1:LookupUserResponse/ns1:UserOptionalFields/ns1:NameInformation/' .
            'ns1:PersonalNameInformation/ns1:StructuredPersonalUserName/' .
            'ns1:Surname'
        );

        
        // TODO: distinguish between permanent and other types of addresses; look
        // at the UnstructuredAddressType field and handle multiple options.
//         $address = $response->xpath(
//             'ns1:LookupUserResponse/ns1:UserOptionalFields/' .
//             'ns1:UserAddressInformation/ns1:PhysicalAddress/' .
//             'ns1:UnstructuredAddress/ns1:UnstructuredAddressData'
//         );
//         $address = explode("\n", trim((string)$address[0]));

           $addresses = $response->xpath('ns1:LookupUserResponse/ns1:UserOptionalFields/'.
                   'ns1:UserAddressInformation');
//            $zip = $response->xpath('ns1:LookupUserResponse/ns1:UserOptionalFields/'.
//                    'ns1:UserAddressInformation/ns1:PhysicalAddress/ns1:StructuredAddress/ns1:PostalCode');
        return array(
            'firstname' => (string)$first[0],
            'lastname' => (string)$last[0],
            'address1' => isset($addresses[0]) ? $this->_addressToString($addresses[0]) : '',
            'address2' => isset($addresses[1]) ? $this->_addressToString($addresses[1]) : '',
//             'zip' =>  isset($zip[0]) ? string($zip) : '',
            'phone' => '',  // TODO: phone number support
            'group' => ''
        );
	
    }
    
    /**
     * converts SimpleXML addres object to string
     * @param unknown $address
     * @return string
     */
    private function _addressToString($address) {

        $address->registerXPathNamespace('ns1', 'http://www.niso.org/2008/ncip');
        $addr = $address->xpath('ns1:PhysicalAddress/ns1:UnstructuredAddress/ns1:UnstructuredAddressData');
        if ($addr) {
            return (string)$addr[0] != null ? (string)$addr[0] : '';
        }        
        
        $street = $address->xpath('ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Street');
        $locality = $address->xpath('ns1:PhysicalAddress/ns1:StructuredAddress/ns1:Locality');
        $postal = $address->xpath('ns1:PhysicalAddress/ns1:StructuredAddress/ns1:PostalCode');
        
        $street = isset($street[0]) ? (string)$street[0] : '';
        $locality = isset($locality[0]) ? (string)$locality[0] : '';
        $postal = isset($postal[0]) ? (string)$postal[0] : '';
        return $street.' '.$locality.' '.$postal;
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @return array       Associative array with 'count' and 'results' keys
     * @access public
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        // TODO
        return array();
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @return array An associative array with key = fund ID, value = fund name.
     * @access public
     */
    public function getFunds()
    {
        // TODO
        return array();
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @return array An associative array with key = dept. ID, value = dept. name.
     * @access public
     */
    public function getDepartments()
    {
        // TODO
        return array();
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     * @access public
     */
    public function getInstructors()
    {
        // TODO
        return array();
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @return array An associative array with key = ID, value = name.
     * @access public
     */
    public function getCourses()
    {
        // TODO
        return array();
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @return mixed An array of associative arrays representing reserve items (or a
     * PEAR_Error object if there is a problem)
     * @access public
     */
    public function findReserves($course, $inst, $dept)
    {
        // TODO
        return array();
    }

    /**
     * Get suppressed records.
     *
     * @return array ID numbers of suppressed records in the system.
     * @access public
     */
    public function getSuppressedRecords()
    {
        // TODO
        return array();
    }

    public function getConfig($param) {
        
        $result = array();
        switch ($param) {
            case "cancelHolds":break;
            case "Holds":
                $result['HMACKeys'] = 'id:title:item';
                break;
            case "Renewals":break;
            default: break;            
        }
        return $result;
    }
    
    public function placeHold($params) {
        if (!$params['id'] || !$params['item'] || !$params['patron']) {
            return;
        }
        
        $patron = $params['patron'];
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
       
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<ns1:NCIPMessage xmlns:ns1="http://www.niso.org/2008/ncip"
                         ns1:version="http://www.niso.org/schemas/ncip/v2_0/imp1/xsd/ncip_v2_0.xsd">'.    
            '<ns1:RequestItem>'.  
            '<ns1:InitiationHeader>'.  
                '<ns1:FromAgencyId>'.  
                    '<ns1:AgencyId>UICdb</ns1:AgencyId>'.  
                '</ns1:FromAgencyId>'.  
                '<ns1:ToAgencyId>'.  
                    '<ns1:AgencyId>UICdb</ns1:AgencyId>'.  
                '</ns1:ToAgencyId>'.  
            '</ns1:InitiationHeader>'.
    
            '<ns1:AuthenticationInput>'.  
                '<ns1:AuthenticationInputData>'.$username.'</ns1:AuthenticationInputData>'.  
                '<ns1:AuthenticationDataFormatType>text</ns1:AuthenticationDataFormatType>'.  
                '<ns1:AuthenticationInputType>Username</ns1:AuthenticationInputType>'.  
            '</ns1:AuthenticationInput>'.  
            '<ns1:AuthenticationInput>'.  
                '<ns1:AuthenticationInputData>'.$password.'</ns1:AuthenticationInputData>'.  
                '<ns1:AuthenticationDataFormatType>text</ns1:AuthenticationDataFormatType>'.  
                '<ns1:AuthenticationInputType>Password</ns1:AuthenticationInputType>'.  
            '</ns1:AuthenticationInput>'.  
    
            '<ns1:BibliographicId>'.  
                    '<ns1:BibliographicRecordId>'.  
                            '<ns1:BibliographicRecordIdentifier>'.$params['id'].'</ns1:BibliographicRecordIdentifier>'.  
                            '<ns1:AgencyId>UICdb</ns1:AgencyId>'.  
                    '</ns1:BibliographicRecordId>'.  
            '</ns1:BibliographicId>'.  
                    
            '<ns1:ItemId>'.  
                 '<ns1:ItemIdentifierValue>'.$params['item'].'</ns1:ItemIdentifierValue>'.  
            '</ns1:ItemId>'.  
    
            '<ns1:RequestType ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes/requesttype/'.
            'requesttype.scm">Hold</ns1:RequestType>'.  
            '<ns1:RequestScopeType ns1:Scheme="http://www.niso.org/ncip/v1_0/imp1/schemes/requestscopetype/'.
            'requestscopetype.scm">Bibliographic Item</ns1:RequestScopeType>'.  
    
        '</ns1:RequestItem>'.  
        '</ns1:NCIPMessage>';
        
        $response = $this->_sendRequest($xml);
        $results = array();
        $results['succsess'] = false;
        if ( $response->xpath('ns1:RequestItemResponse/ns1:Problem') == null ){
            $results['success'] = true;
        }
    	return $results;
    }


}

?>
