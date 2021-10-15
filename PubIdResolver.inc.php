<?php

/**
 * @file plugins/gateways/pubIdResolver/PubIdResolver.inc.php
 *
 * Copyright (c) 2021 Yasiel Pérez Vera
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubIdResolver
 * @ingroup plugins_gateways_pubidresolver
 *
 * @brief Simple Pub id resolver gateway plugin
 */

import('lib.pkp.classes.plugins.GatewayPlugin');

class PubIdResolver extends GatewayPlugin {
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		$success = parent::register($category, $path, $mainContextId);
		$this->addLocaleData();
		return $success;
	}

	/**
	 * Get the name of the settings file to be installed on new journal
	 * creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'pubIdResolver';
	}

	function getDisplayName() {
		return __('plugins.gateways.pubIdResolver.displayName');
	}

	function getDescription() {
		return __('plugins.gateways.pubIdResolver.description');
	}

	/**
	 * Handle fetch requests for this plugin.
	 */
	function fetch($args, $request) {
		if (!$this->getEnabled()) {
			return false;
		}
		$journal = $request->getJournal();		
		$journalId = $journal->getId();	
		$pubId = implode('/', $args);
		$pubIdPlugins = (array) PluginRegistry::loadCategory('pubIds', true, $journalId);
		$exportInfo = substr($_SERVER['REQUEST_URI'],-1) == "?";
        foreach ($pubIdPlugins as $pubIdPlugin) {
			$pubIdType = $pubIdPlugin->getPubIdType();
			
			$DAOs = DAORegistry::getDAOs();
			$issueDAO = $DAOs['IssueDAO'];
			$articleGalleyDAO = $DAOs['ArticleGalleyDAO'];
			$article = false;
			
			if (array_key_exists('SubmissionDAO', $DAOs)){
				$submissionDAO = $DAOs['SubmissionDAO'];
				$article = $submissionDAO->getByPubId($pubIdType, $pubId, $journalId);
			}
			if (array_key_exists('PublishedArticleDAO', $DAOs)){
				$publishedArticleDAO = $DAOs['PublishedArticleDAO'];
				$article = $publishedArticleDAO->getPublishedArticleByPubId($pubIdType, $pubId, $journalId);
			}		
			if($article) {
				$resolvingURL = $pubIdPlugin->getResolvingURL($journalId, $pubId);
				$articleURL = $request->url($journal->getPath(), 'article', 'view', $article->getBestArticleId());
				if ($exportInfo) $this->createArticleERC($article, $pubIdType, $resolvingURL, $articleURL);
				$request->redirect(null, 'article', 'view', $article->getBestArticleId());
				break;
			}
			else {
				$issue = $issueDAO->getByPubId($pubIdType, $pubId, $journalId);				
				if($issue)
				{
					$resolvingURL = $pubIdPlugin->getResolvingURL($journalId, $pubId);
					$issueURL = $request->url($journal->getPath(), 'issue', 'view', $issue->getBestIssueId());
					if ($exportInfo) $this->createIssueERC($issue, $pubIdType, $resolvingURL, $journal->getLocalizedName(), $issueURL);
					$request->redirect(null, 'issue', 'view', $issue->getBestIssueId());
					break;
				}
				else {
					$submissionGalley = $articleGalleyDAO->getGalleyByPubId($pubIdType, $pubId);
					if($submissionGalley){
						$publicationId = $submissionGalley->_data['publicationId'] ? $submissionGalley->_data['publicationId'] : $submissionGalley->_data['submissionId'];
						$resolvingURL = $pubIdPlugin->getResolvingURL($journalId, $pubId);
						$galleyURL = $request->url($journal->getPath(), 'article', 'view', [$publicationId, $submissionGalley->getBestGalleyId()]);
						if ($exportInfo) $this->createGalleyERC($submissionGalley, $pubIdType, $resolvingURL, $galleyURL, $journal->getLocalizedName());
						$request->redirect(null, 'article', 'view',[$publicationId, $submissionGalley->getBestGalleyId()]);
						break;
					}
				}
			}			
		}

		// Failure.
		header('HTTP/1.0 404 Not Found');
		$templateMgr = TemplateManager::getManager($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);
		$templateMgr->assign('message', 'plugins.gateways.pubIdResolver.errors.errorMessage');
		$templateMgr->display('frontend/pages/message.tpl');
		exit;
	}
	
	function exportInfo($who, $what, $when, $where, $how, $target) {
		if (empty(strip_tags(trim($who)))) $who = "(:unkn) Anonymous";
		if (empty(strip_tags(trim($what)))) $what = "(:unas) Untitled";
		if (empty(strip_tags(trim($when)))) $when = "(:unav) Undated";
		if (empty(strip_tags(trim($where)))) $where = "(:none) Unidentified";
		if (empty(strip_tags(trim($how)))) $how = "(:unav) Untyped";
		if (empty(strip_tags(trim($target)))) $target = "(:unav) Untarget";
		
		header('HTTP/1.0 200 OK');
		header('Content-Type: text/plain; charset=UTF-8');
		
		print_r("erc:  
who: $who
what: $what
when: $when
where: $where
how: $how
_t: $target");
		exit;
	}
	
	function createIssueERC($object, $pubIdType, $resolvingURL, $journalName, $issueURL)
	{
		$what = "";
		if (!empty($object->getVolume()) && $object->getShowVolume()) $what .= "Vol. " . $object->getVolume();
		if (!empty($object->getNumber()) && $object->getShowNumber()) $what .= " Num. " . $object->getNumber();
		if (!empty($object->getYear()) && $object->getShowYear()) $what .= " (" . $object->getYear() . ")";
		if (!empty($object->getLocalizedTitle()) && $object->getShowTitle()) $what .= ": " . $object->getLocalizedTitle();
		$when = $object->getDatePublished();
		$where = $object->getStoredPubId($pubIdType) . " (" . $resolvingURL . ")";
		$who = $journalName;
		$how = "(:mtype text) issue";
		$target = $issueURL;
		$this->exportInfo($who, $what, $when, $where, $how, $target);
	}
	function createArticleERC($object, $pubIdType, $resolvingURL, $articleURL)
	{
		$what = $object->getLocalizedTitle();
		$when = $object->getDatePublished();
		$where = $object->getStoredPubId($pubIdType) . " (" . $resolvingURL . ")";
		$who = "";
		$authors = $object->getAuthors($article);
        foreach ($authors as $author) {
            $who .= $author->getFullName(false, true) . "; ";
        }
		$who = substr($who, 0, -2);
		
		$how = "(:mtype text) article";
		$target = $articleURL;
		
		$this->exportInfo($who, $what, $when, $where, $how, $target);
	}
	
	function createGalleyERC($object, $pubIdType, $resolvingURL, $galleyURL, $journalName)
	{
		$when = $object->getFile()->getDateModified();
        $what = $object->getFile()->getLocalizedData("name");
		$where = $object->getStoredPubId($pubIdType) . " (" . $resolvingURL . ")";
		$who = $journalName;		
		$how = "(:mtype data) " . $object->getFileType();
		$target = $galleyURL;
		$this->exportInfo($who, $what, $when, $where, $how, $target);
	}
}


