<?php

namespace Typemill\Controllers;

use DI\Container;
use Slim\Routing\RouteContext;
use Typemill\Models\StorageWrapper;
use Typemill\Events\OnTwigLoaded;

abstract class Controller
{
	# holds the container
	protected $c;

	# holds the settings
	protected $settings;

	protected $routeParser;

	public function __construct(Container $container)
	{
		$this->c 			= $container;

		$this->settings 	= $container->get('settings');

		$this->routeParser 	= $container->get('routeParser');

		$this->c->get('dispatcher')->dispatch(new OnTwigLoaded(false), 'onTwigLoaded');		
	}

/*
	public function getCsrfData($request)
	{
        $nameKey = $this->csrf->getTokenNameKey();
        $valueKey = $this->csrf->getTokenValueKey();    
        
        return [
            $nameKey => $request->getAttribute($nameKey),
            $valueKey => $request->getAttribute($valueKey)
        ];
	}	
*/
	protected function settingActive($setting)
	{
		if(isset($this->settings[$setting]) && $this->settings[$setting])
		{
			return true;
		}

		return false;
	}

	protected function hasChanged($input, $stored, $field)
	{
		if(isset($input[$field]) && isset($stored[$field]) && $input[$field] == $stored[$field])
		{
			return false;
		}
		if(!isset($input[$field]) && !isset($input[$field]))
		{
			return false;
		}
		return true;
	}

	protected function getItem($navigation, $url, $urlinfo)
	{
		$url 				= $this->removeEditorFromUrl($url);
		$langattr 			= $this->settings['langattr'];

		if($url == '/')
		{
			$keyPathArray 		= [''];
		}

		else
		{
			$extendedNavigation	= $navigation->getExtendedNavigation($urlinfo, $langattr);

			$pageinfo 			= $extendedNavigation[$url] ?? false;
			if(!$pageinfo)
			{
				# page not found
				return false;
		    }

			$keyPathArray 		= explode(".", $pageinfo['keyPath']);

		}

		$draftNavigation 	= $navigation->getDraftNavigation($urlinfo, $langattr);
		
		$item				= $navigation->getItemWithKeyPath($draftNavigation, $keyPathArray, $urlinfo['basepath']);

		return $item;
	}		

	protected function removeEditorFromUrl($url)
	{
		$url = trim($url, '/');

		$url = str_replace('tm/content/visual', '', $url);
		$url = str_replace('tm/content/raw', '', $url);

		$url = trim($url, '/');

		return '/' . $url;
	}

	protected function addDatasets(array $formDefinitions)
	{		
		foreach($formDefinitions as $fieldname => $field)
		{
			if(isset($field['type']) && $field['type'] == 'fieldset')
			{
				$formDefinitions[$fieldname]['fields'] = $this->addDatasets($field['fields']);
			}

			if(isset($field['type']) && ($field['type'] == 'select' ) )
			{
				# always add null as first option in selectboxes.
				$options = [null => null];
				
				if(isset($field['options']) && is_array($field['options']))
				{
					$options = array_merge($options, $field['options']);
				}

				if(isset($field['dataset']) && ($field['dataset'] == 'userroles' ))
				{
					foreach($this->c->get('acl')->getRoles() as $userrole)
					{
						$options[$userrole] = $userrole;
					}
				}

				$formDefinitions[$fieldname]['options'] = $options;
			}
		}

		return $formDefinitions;
	}

	protected function userroleIsAllowed($userrole, $resource, $action)
	{
		$acl = $this->c->get('acl');

		if($acl->isAllowed($userrole, $resource, $action))
		{
			return true;
		}

		return false;
	}

	protected function userIsAllowed($username, $pagemeta)
	{
		if(
			isset($pagemeta['meta']['owner']) && 
			$pagemeta['meta']['owner'] && 
			$pagemeta['meta']['owner'] !== '' 
		)
		{
			$allowedusers = array_map('trim', explode(",", $pagemeta['meta']['owner']));
			if(
				in_array($username, $allowedusers)
			)
			{
				return true;
			}
		}

		return false;
	}


	# used to protect api access, can we do it with middleware?
	protected function validateRights($userrole, $resource, $action)
	{
		# check ownership. THIS WILL FAIL ANYWAY!!!
		# MAYBE WE SHOUD ADD THIS CHECK INTO MIDDLEWARE, TOO ?

		$acl = $this->c->get('acl');

		if($acl->isAllowed($userrole, $resource, $action))
		{
			return true;
		}

		die("PLEASE UPDATE THE METHOD validateRights in controller.php");

		$writeMeta = new writeMeta();
		$pagemeta = $writeMeta->getPageMeta($this->settings, $this->item);

		if(
			isset($pagemeta['meta']['owner']) && 
			$pagemeta['meta']['owner'] && 
			$pagemeta['meta']['owner'] !== '' 
		)
		{
			$allowedusers = array_map('trim', explode(",", $pagemeta['meta']['owner']));
			if(
				isset($_SESSION['user']) && 
				in_array($_SESSION['user'], $allowedusers)
			)
			{
				return true;
			}
		}

		return false;
	}
}