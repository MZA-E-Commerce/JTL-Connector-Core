<?php
namespace Jtl\Connector\Core\Event\Product;

use Symfony\Component\EventDispatcher\Event;
use Jtl\Connector\Core\Model\QueryFilter;


class ProductBeforeStatisticEvent extends Event
{
    const EVENT_NAME = 'product.before.statistic';

	protected $filter;

    public function __construct(QueryFilter &$filter)
    {
		$this->filter = $filter;
    }

    public function getFilter()
    {
        return $this->filter;
	}
	
}
