<?php
/****************************************************************************
 *   Copyright (C) 2009 by Vladlen Y. Koshelev                              *
 *                                                                          *
 *   This program is free software; you can redistribute it and/or modify   *
 *   it under the terms of the GNU Lesser General Public License as         *
 *   published by the Free Software Foundation; either version 3 of the     *
 *   License, or (at your option) any later version.                        *
 *                                                                          *
 ****************************************************************************/

	/**
	 * @ingroup OQL
	**/
	final class OqlGreedyAlternationRuleParseStrategy extends OqlGrammarRuleParseStrategy
	{
		/**
		 * @return OqlGreedyAlternationRuleParseStrategy
		**/
		public static function me()
		{
			return Singleton::getInstance(__CLASS__);
		}
		
		/**
		 * @return OqlSyntaxNode
		**/
		public function parse(
			OqlGrammarRule $rule,
			OqlTokenizer $tokenizer,
			$silent = false
		)
		{
			Assert::isTrue($rule instanceof OqlGreedyAlternationRule);
			
			$maxLength = 0;
			$maxNode = null;
			$index = $tokenizer->getIndex();
			
			foreach ($rule->getList() as $ruleItem) {
				if (
					($node = $ruleItem->process($tokenizer, true))
					&& ($maxLength < $tokenizer->getIndex() - $index)
				) {
					$maxLength = $tokenizer->getIndex() - $index;
					$maxNode = $node;
				}
				
				$tokenizer->setIndex($index);
			}
			
			if ($maxNode)
				$tokenizer->setIndex($index + $maxLength);
			// FIXME: error message
			elseif (!$silent)
				$this->raiseError($tokenizer, 'expected');
			
			return $maxNode;
		}
	}
?>