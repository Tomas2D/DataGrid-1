<?php
/**
 * This file is part of the Mesour DataGrid (http://grid.mesour.com)
 *
 * Copyright (c) 2015-2016 Matouš Němec (http://mesour.com)
 *
 * For full licence and copyright please view the file licence.md in root of this project
 */

namespace Mesour\DataGrid\Extensions\Pager;

use Mesour;

/**
 * @author Matouš Němec <matous.nemec@mesour.com>
 */
class PagerExtension extends Mesour\UI\AdvancedPager implements IPager
{

	use Mesour\Components\Security\Authorised;

	/** @var Mesour\Components\Utils\Html|string */
	private $createdPager;

	private $disabled = false;

	public function handleSetPage($page = null)
	{
		if ($this->isDisabled()) {
			throw new Mesour\InvalidStateException('Cannot change page if extension is disabled.');
		}
		$this->getGrid()->reset();
		parent::handleSetPage($page);
	}

	/** @return Mesour\DataGrid\ExtendedGrid */
	public function getGrid()
	{
		return $this->getParent();
	}

	/**
	 * @return bool
	 */
	public function isDisabled()
	{
		return $this->disabled;
	}

	public function setDisabled($disabled = true)
	{
		$this->disabled = $disabled;
		return $this;
	}

	public function gridCreate($data = [])
	{
	}

	public function createInstance(Mesour\DataGrid\Extensions\IExtension $extension, $name = null)
	{
	}

	public function reset($hard = false)
	{
		parent::reset();
	}

	public function afterGetCount($count)
	{
		$this->setCount($count);
		$this->beforeRender();
	}

	public function beforeFetchData($data = [])
	{
		$this->createdPager = $this->getForCreate();
		$itemsPerPage = $this->getPaginator()->getItemsPerPage();
		$this->getGrid()->getSource()->applyLimit($itemsPerPage, ($this->getPaginator()->getPage() - 1) * $itemsPerPage);
	}

	public function afterFetchData($currentData, $data = [], $rawData = [])
	{
	}

	public function attachToRenderer(Mesour\DataGrid\Renderer\IGridRenderer $renderer, $data = [], $rawData = [])
	{
		$pagerWrapper = $this->getGrid()->getPagerPrototype();
		$pagerWrapper->add($this->createdPager);
		$renderer->setComponent('pager', $pagerWrapper);
	}

}
