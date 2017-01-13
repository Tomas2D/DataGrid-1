<?php
/**
 * This file is part of the Mesour DataGrid (http://grid.mesour.com)
 *
 * Copyright (c) 2015-2016 Matouš Němec (http://mesour.com)
 *
 * For full licence and copyright please view the file licence.md in root of this project
 */

namespace Mesour\DataGrid\Column;

use Mesour;
use Mesour\Table\Render;

/**
 * @author Matouš Němec <matous.nemec@mesour.com>
 */
class Container extends Filtering implements IExportable, IContainer
{

	use Mesour\Icon\HasIcon;

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return Text
	 */
	public function addText($name, $header = null)
	{
		$column = new Text;
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return Date
	 */
	public function addDate($name, $header = null)
	{
		$column = new Date;
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return self
	 */
	public function addContainer($name, $header = null)
	{
		$column = new self;
		$column->setFiltering(false)
			->setOrdering(false);
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return Image
	 */
	public function addImage($name, $header = null)
	{
		$column = new Image;
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return Status
	 */
	public function addStatus($name, $header = null)
	{
		$column = new Status;
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @param string|null $header
	 * @return Template
	 */
	public function addTemplate($name, $header = null)
	{
		$column = new Template;
		$this->setColumn($column, $name, $header);
		return $column;
	}

	/**
	 * @param string $name
	 * @return Mesour\UI\Button(
	 * @throws Mesour\InvalidArgumentException
	 */
	public function addButton($name)
	{
		$button = new Mesour\UI\Button($name);
		$button->setSize('btn-sm');
		$this->addComponent($button);
		return $button;
	}

	/**
	 * @param string $name
	 * @return Mesour\UI\DropDown
	 * @throws Mesour\InvalidArgumentException
	 */
	public function addDropDown($name)
	{
		$dropDown = new Mesour\UI\DropDown($name);
		$dropDown->getMainButton()
			->setSize('btn-sm');
		$this->addComponent($dropDown);
		return $dropDown;
	}

	public function attachToFilter(Mesour\DataGrid\Extensions\Filter\IFilter $filter, $hasCheckers)
	{
		parent::attachToFilter($filter, $hasCheckers);
		$item = $filter->addTextFilter($this->getName(), $this->getHeader());
		$this->setUpFilterItem($item, $hasCheckers);
	}

	protected function setColumn(Render\IColumn $column, $name, $header = null)
	{
		$column->setHeader($header);
		return $this[$name] = $column;
	}

	public function getHeaderAttributes()
	{
		return [
			'class' => 'grid-column-' . $this->getName() . ' column-container',
		];
	}

	public function getBodyAttributes($data, $need = true, $rawData = [])
	{
		return parent::getBodyAttributes($data, false, $rawData);
	}

	public function getBodyContent($data, $rawData, $export = false)
	{
		if (
			!isset($data->{$this->getName()})
			&& (property_exists($data, $this->getName()) && !is_null($data->{$this->getName()}))
			&& ($this->hasFiltering() || $this->hasOrdering())
		) {
			throw new Mesour\OutOfRangeException('Column with name ' . $this->getName() . ' does not exists in data source.');
		}

		$onlyButtons = true;
		$container = Mesour\Components\Utils\Html::el('span', ['class' => 'container-content']);
		foreach ($this as $control) {
			if (!$control instanceof Mesour\UI\Button && !$control instanceof Mesour\UI\DropDown) {
				$onlyButtons = false;
			}
			$span = Mesour\Components\Utils\Html::el('span');

			if ($control instanceof Render\IColumn) {
				$span->addAttributes($control->getHeaderAttributes());
				$span->addAttributes($control->getBodyAttributes($data));
			}

			$fromCallback = $this->tryInvokeCallback([$this, $rawData, $span, $control]);

			if ($fromCallback === self::NO_CALLBACK) {
				if ($control instanceof Render\IColumn) {
					$content = $control->getBodyContent($data, $rawData);
					if (!is_null($content)) {
						$span->add($content);
					}
				} elseif ($control instanceof Mesour\Components\Control\IOptionsControl) {
					$control->setOption('data', $data);
					$span->add($control->create());
				} else {
					$span->add($control->render());
				}
			} else {
				$span->add($fromCallback);
			}

			$container->add($span);
			$container->add(' ');
		}
		if ($onlyButtons) {
			$container->class('only-buttons', true);
		}
		return $export ? trim(strip_tags($container)) : $container;
	}

}
