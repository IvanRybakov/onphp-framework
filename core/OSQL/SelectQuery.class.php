<?php
/****************************************************************************
 *   Copyright (C) 2004-2007 by Konstantin V. Arkhipov, Anton E. Lebedevich *
 *                                                                          *
 *   This program is free software; you can redistribute it and/or modify   *
 *   it under the terms of the GNU General Public License as published by   *
 *   the Free Software Foundation; either version 2 of the License, or      *
 *   (at your option) any later version.                                    *
 *                                                                          *
 ****************************************************************************/

/*
	$Id$
	
	06 Oct 2005: Skeleton merged back.
	
	07 Jun 2005: Separation of {get,set}ters.
	
	28 Mar 2005: Fourth rewrite by Anton.
	
	04 Jan 2005: Third rewrite. Main goal now - simplicity.
*/

	/**
	 * @ingroup OSQL
	**/
	final class SelectQuery
		extends QuerySkeleton
		implements Named, JoinCapableQuery, Aliased
	{
		private $distinct		= false;

		private $name			= null;
		
		private $joiner			= null;
		
		/// @see FetchStrategy
		private $strategyId		= null;
		
		private $limit			= null;
		private $offset			= null;

		private $fields			= array();
		
		private $order			= null;
		
		private $group			= array();
		
		private $having			= null;
		
		private $aliases		= array();
		
		public function __construct()
		{
			$this->joiner = new Joiner();
			$this->order = new OrderChain();
		}
		
		public function __clone()
		{
			$this->joiner = clone $this->joiner;
			$this->order = clone $this->order;
		}
		
		public function getFetchStrategyId()
		{
			return $this->strategyId;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function setFetchStrategyId($id)
		{
			$this->strategyId = $id;
			
			return $this;
		}
		
		public function hasAliasInside($alias)
		{
			return isset($this->aliases[$alias]);
		}
		
		public function getAlias()
		{
			return $this->name;
		}
		
		public function getName()
		{
			return $this->name;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function setName($name)
		{
			$this->name = $name;
			$this->aliases[$name] = true;
			
			return $this;
		}

		/**
		 * @return SelectQuery
		**/
		public function distinct()
		{
			$this->distinct = true;
			return $this;
		}

		public function isDistinct()
		{
			return $this->distinct;
		}

		/**
		 * @return SelectQuery
		**/
		public function unDistinct()
		{
			$this->distinct = false;
			return $this;
		}
		
		public function hasJoinedTable($table)
		{
			return $this->joiner->hasJoinedTable($table);
		}

		/**
		 * @return SelectQuery
		**/
		public function join($table, LogicalObject $logic, $alias = null)
		{
			$this->joiner->join(new SQLJoin($table, $logic, $alias));
			$this->aliases[$alias] = true;
			
			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function leftJoin($table, LogicalObject $logic, $alias = null)
		{
			$this->joiner->leftJoin(new SQLLeftJoin($table, $logic, $alias));
			$this->aliases[$alias] = true;
			
			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function setOrderChain(OrderChain $chain)
		{
			$this->order = $chain;
			
			return $this;
		}

		/**
		 * @return SelectQuery
		**/
		public function orderBy($field, $table = null)
		{
			$this->order->add($this->makeOrder($field, $table));
			
			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function prependOrderBy($field, $table = null)
		{
			$this->order->prepend($this->makeOrder($field, $table));

			return $this;
		}

		/**
		 * @throws WrongStateException
		 * @return SelectQuery
		**/
		public function desc()
		{
			if (!$last = $this->order->getLast())
				throw new WrongStateException('no fields to sort');

			$last->desc();

			return $this;
		}
		
		/**
		 * @throws WrongStateException
		 * @return SelectQuery
		**/
		public function asc()
		{
			if (!$last = $this->order->getLast())
				throw new WrongStateException('no fields to sort');

			$last->asc();

			return $this;
		}

		/**
		 * @return SelectQuery
		**/
		public function groupBy($field, $table = null)
		{
			if ($field instanceof DialectString)
				$this->group[] = $field;
			else 
				$this->group[] =
					new DBField($field, $this->getLastTable($table));

			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function having(LogicalObject $exp)
		{
			$this->having = $exp;
			
			return $this;
		}
		
		public function getLimit()
		{
			return $this->limit;
		}
		
		public function getOffset()
		{
			return $this->offset;
		}

		/**
		 * @throws WrongArgumentException
		 * @return SelectQuery
		**/
		public function limit($limit = null, $offset = null)
		{
			if ($limit !== null)
				Assert::isPositiveInteger($limit, 'invalid limit specified');
				
			if ($offset !== null)
				Assert::isInteger($offset, 'invalid offset specified');
			
			$this->limit = $limit;
			$this->offset = $offset;
			
			return $this;
		}

		/**
		 * @return SelectQuery
		**/
		public function from($table, $alias = null)
		{
			$this->joiner->from(new FromTable($table, $alias));
			
			$this->aliases[$alias] = true;

			return $this;
		}
		
		/**
		 * BOVM: achtung!
		 * 
		 * @throws WrongArgumentException
		 * @return SelectQuery
		**/
		public function get($field, $alias = null)
		{
			$table = null;
			if (is_object($field)) {
				if (
					($field instanceof DBField)
					&& ($field->getTable() === null)
				) {
					$this->fields[] = new SelectField(
						$field->setTable($this->getLastTable()),
						$alias
					);
				} elseif ($field instanceof DialectString) {
					$this->fields[] = new SelectField($field, $alias);
					
					if ($field instanceof Aliased)
						$this->aliases[$field->getAlias()] = true;
					elseif ($alias)
						$this->aliases[$alias] = true;
				} else
					throw new WrongArgumentException('unknown field type');
				
				return $this;
				
			} elseif (false !== strpos($field, '*'))
				throw new WrongArgumentException(
					'do not fsck with us: specify fields explicitly'
				);
			elseif (false !== strpos($field, '.'))
				throw new WrongArgumentException(
					'forget about dot: use DBField'
				);
			else
				$fieldName = $field;
				
			$this->fields[] = new SelectField(
				new DBField($fieldName, $this->getLastTable($table)), $alias
			);
			
			$this->aliases[$alias] = true;

			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function multiGet(/* ... */)
		{
			$size = func_num_args();
		
			if ($size && $args = func_get_args())
				for ($i = 0; $i < $size; ++$i)
					$this->get($args[$i]);
		
			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function arrayGet($array, $prefix = null)
		{
			$size = count($array);
			
			if ($prefix) {
				for ($i = 0; $i < $size; ++$i) {
					if ($array[$i] instanceof DialectString) {
						if ($array[$i] instanceof DBField) {
							$alias = $prefix.$array[$i]->getField();
						} else {
							if ($array[$i] instanceof SQLFunction) {
								$alias =
									$array[$i]->setAlias(
										$prefix.$array[$i]->getName()
									)->
									getAlias();
							} else {
								$alias = $array[$i];
							}
						}
					} else {
						$alias = $prefix.$array[$i];
					}

					$this->get($array[$i], $alias);
					$this->aliases[$alias] = true;
				}
			} else {
				for ($i = 0; $i < $size; ++$i) {
					$this->get($array[$i]);
				}
			}
					
			return $this;
		}

		public function getFieldsCount()
		{
			return count($this->fields);
		}
		
		public function getTablesCount()
		{
			return $this->joiner->getTablesCount();
		}
		
		public function getFieldNames()
		{
			$nameList = array();
			
			foreach ($this->fields as $field) {
				if ($field instanceof SelectField)
					if ($alias = $field->getAlias()) {
						$nameList[] = $alias;
						continue;
					}
				
				$nameList[] = $field->getName();
			}
			
			return $nameList;
		}
		
		public function toDialectString(Dialect $dialect)
		{
			$fieldList = array();
			foreach ($this->fields as $field) {
				
				if ($field instanceof SelectQuery) {
					
					Assert::isTrue(
						null !== $alias = $field->getName(),
						'can not use SelectQuery without name as get field'
					);
					
					$fieldList[] =
						"({$field->toDialectString($dialect)}) AS ".
						$dialect->quoteField($alias);
				} else
					$fieldList[] = $field->toDialectString($dialect);
			}

			$query = 
				'SELECT '.($this->distinct ? 'DISTINCT ' : null)
				.implode(', ', $fieldList)
				.$this->joiner->toDialectString($dialect);
				
			// WHERE
			$query .= parent::toDialectString($dialect);

			if ($this->group) {
				$groupList = array();
				
				foreach ($this->group as $group)
					$groupList[] = $group->toDialectString($dialect);
				
				if ($groupList)
					$query .= " GROUP BY ".implode(', ', $groupList);
			}
			
			if ($this->having)
				$query .= ' HAVING '.$this->having->toDialectString($dialect);
			
			if ($this->order->getCount()) {
				$query .= ' ORDER BY '.$this->order->toDialectString($dialect);
			}
			
			if ($this->limit)
				$query .= " LIMIT {$this->limit}";
			
			if ($this->offset)
				$query .= " OFFSET {$this->offset}";
			
			return $query;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function dropFields()
		{
			$this->fields = array();
			return $this;
		}
		
		/**
		 * @return SelectQuery
		**/
		public function dropOrder()
		{
			$this->order = new OrderChain();
			return $this;
		}

		private function getLastTable($table = null)
		{
			if (!$table && ($last = $this->joiner->getLastTable()))
				return $last;
			
			return $table;
		}
		
		/**
		 * @return OrderBy
		**/
		private function makeOrder($field, $table = null)
		{
			if (
				$field instanceof OrderBy
				|| $field instanceof DialectString
			)
				return $field;
			else 
				return
					new OrderBy(
						new DBField($field, $this->getLastTable($table))
					);
		}
	}
?>