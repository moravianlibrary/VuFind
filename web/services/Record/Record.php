<?php
/**
 * Base class shared by most Record module actions.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  Controller_Record
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'Action.php';

require_once 'sys/Language.php';

require_once 'RecordDrivers/Factory.php';
require_once 'sys/ResultScroller.php';
require_once 'sys/VuFindDate.php';

/**
 * Base class shared by most Record module actions.
 *
 * @category VuFind
 * @package  Controller_Record
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class Record extends Action
{
    protected $recordDriver;
    protected $cacheId;
    protected $db;
    protected $catalog;
    protected $errorMsg;
    protected $infoMsg;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct()
    {
        global $configArray;
        global $interface;
        global $user;

        //$interface->caching = 1;

        // Define Default Tab
        $defaultTab = isset($configArray['Site']['defaultRecordTab']) ?
            $configArray['Site']['defaultRecordTab'] : 'Holdings';
        $tab = (isset($_GET['action'])) ? $_GET['action'] : $defaultTab;
        $interface->assign('tab', $tab);

        // Store ID of current record (this is needed to generate appropriate
        // links, and it is independent of which record driver gets used).
        $interface->assign('id', $_REQUEST['id']);

        // Setup Search Engine Connection
        $this->db = ConnectionManager::connectToIndex();
        
        // Connect to Database
        $this->catalog = ConnectionManager::connectToCatalog();
        
        // Set up object for formatting dates and times:
        $this->dateFormat = new VuFindDate();
        
        // Register Library Catalog Account
        if (isset($_POST['submit']) && !empty($_POST['submit'])) {
            if (isset($_POST['cat_username']) 
                && isset($_POST['cat_password'])
            ) {
                $result = UserAccount::processCatalogLogin(
                    $_POST['cat_username'], $_POST['cat_password']
                );
                if ($result) {
                    $interface->assign('user', $user);
                } else {
                    $interface->assign('loginError', 'Invalid Patron Login');
                }
            }
        }

        // Retrieve the record from the index
        if (!($record = $this->db->getRecord($_REQUEST['id']))) {
            PEAR::raiseError(new PEAR_Error('Record Does Not Exist'));
        }
        $this->recordDriver = RecordDriverFactory::initRecordDriver($record);

        if ($this->recordDriver->hasRDF()) {
            $interface->assign(
                'addHeader', '<link rel="alternate" type="application/rdf+xml" ' .
                'title="RDF Representation" href="' . $configArray['Site']['url'] .
                '/Record/' . urlencode($_REQUEST['id']) . '/RDF" />' . "\n"
            );
        }
        $interface->assign('coreMetadata', $this->recordDriver->getCoreMetadata());

        // Determine whether to display book previews
        if (isset($configArray['Content']['previews'])) {
            $providers = explode(',', $configArray['Content']['previews']);
            $interface->assign('showPreviews', true);
            foreach ($providers as $provider) {
                switch ($provider) {
                case 'Google':
                    $interface->assign('showGBSPreviews', true);
                    break;
                case 'OpenLibrary':
                    $interface->assign('showOLPreviews', true);
                    break;
                case 'HathiTrust':
                    $interface->assign('showHTPreviews', true);
                    break;
                }
            }
        }

        // Set flags that control which tabs are displayed:
        if (isset($configArray['Content']['reviews'])) {
            $interface->assign('hasReviews', $this->recordDriver->hasReviews());
        }
        if (isset($configArray['Content']['excerpts'])) {
            $interface->assign('hasExcerpt', $this->recordDriver->hasExcerpt());
        }
        $interface->assign('hasTOC', $this->recordDriver->hasTOC());

        // Assign the next/previous record data:
        $scroller = new ResultScroller();
        $scrollData = $scroller->getScrollData($_REQUEST['id']);
        $interface->assign('previousRecord', $scrollData['previousRecord']);
        $interface->assign('nextRecord', $scrollData['nextRecord']);
        $interface->assign('currentRecordPosition', $scrollData['currentPosition']);
        $interface->assign('resultTotal', $scrollData['resultTotal']);

        // Retrieve User Search History
        $interface->assign(
            'lastsearch',
            isset($_SESSION['lastSearchURL']) ? $_SESSION['lastSearchURL'] : false
        );

        $this->cacheId = 'Record|' . $_REQUEST['id'] . '|' . get_class($this);
        if (!$interface->is_cached($this->cacheId)) {
            // Find Similar Records
            $similar = $this->db->getMoreLikeThis($_REQUEST['id']);

            // Send the similar items to the template; if there is only one, we need
            // to force it to be an array or things will not display correctly.
            if (count($similar['response']['docs']) > 0) {
                $interface->assign('similarRecords', $similar['response']['docs']);
            }

            // Find Other Editions
            $editions = $this->recordDriver->getEditions();
            if (!PEAR::isError($editions)) {
                $interface->assign('editions', $editions);
            }
        }

        // Send down text for inclusion in breadcrumbs
        $interface->assign('breadcrumbText', $this->recordDriver->getBreadcrumb());

        // Send down OpenURL for COinS use:
        $interface->assign('openURL', $this->recordDriver->getOpenURL());

        // Send down legal export formats (if any):
        $interface->assign('exportFormats', $this->recordDriver->getExportFormats());

        // Set AddThis User
        $interface->assign(
            'addThis', isset($configArray['AddThis']['key'])
            ? $configArray['AddThis']['key'] : false
        );

        // Set Proxy URL
        if (isset($configArray['EZproxy']['host'])) {
            $interface->assign('proxy', $configArray['EZproxy']['host']);
        }

        // Get Messages
        $this->infoMsg = isset($_GET['infoMsg']) ? $_GET['infoMsg'] : false;
        $this->errorMsg = isset($_GET['errorMsg']) ? $_GET['errorMsg'] : false;

        // begin of modifications for Aleph
        list($bib, $sys_no) = split('-', $_GET['id'], 2);
        $fullViewURL = "http://aleph.mzk.cz:80/F?func=direct&doc_number=$sys_no&local_base=$bib&format=999";
        $holdingsURL = "http://aleph.mzk.cz:80/F?func=item-global&doc_library=$bib&doc_number=$sys_no";
        $permaLink = $configArray['Site']['url'] . "/Record/" . $bib . "-" . $sys_no;
        $SFXURL = "http://sfx.jib.cz/sfxlcl3?sid=ALEPH:$bib&pid=DocNumber=$sys_no,Ip=alucard,Port=9991&title=";
        $interface->assign('fullViewURL', $fullViewURL);
        $interface->assign('holdingsURL', $holdingsURL);
        $interface->assign('permaLink', $permaLink);
        $interface->assign('SFXURL', $SFXURL);
        /*if (count($this->recordDriver->getPublicationDates()) == 0) {
           $eod = $this->recordDriver->getPublicationDates() < 1890;
        } else {
           $eod = max($this->recordDriver->getPublicationDates()) < 1890;
        }
        //$eod = max($this->recordDriver->getLeaderDate()) < 1890;
        $interface->assign('EOD', $eod);*/
        $interface->assign('sys_no', $sys_no);

        // end of modifications for Aleph
    }

    /**
     * Record a record hit to the statistics index when stat tracking is enabled;
     * this is called by the Home action.
     *
     * @return void
     * @access public
     */
    public function recordHit()
    {
        global $configArray;

        if ($configArray['Statistics']['enabled']) {
            // Setup Statistics Index Connection
            $solrStats = ConnectionManager::connectToIndex('SolrStats');

            // Save Record View
            $solrStats->saveRecordView($this->recordDriver->getUniqueID());
            unset($solrStats);
        }
    }
}

?>
