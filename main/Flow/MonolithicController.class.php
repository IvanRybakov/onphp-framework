<?php
/***************************************************************************
 *   Copyright (C) 2006 by Anton E. Lebedevich, Konstantin V. Arkhipov     *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */
	
	/**
	 * @ingroup Flow
	**/
	abstract class MonolithicController extends BaseEditor
	{
		public function __construct(Prototyped $subject)
		{
			$this->commandMap = array(
				'drop'		=> 'doDrop',
				'save'		=> 'doSave',
				'edit'		=> 'doEdit',
				'add'		=> 'doAdd'
			);
			
			parent::__construct($subject);
		}
		
		/**
		 * @return ModelAndView
		**/
		public function handleRequest(HttpRequest $request)
		{
			$this->map->import($request);
			
			$form = $this->map->getForm();
			
			if ($command = $form->getValue('action')) {
				$mav = $this->{$this->commandMap[$command]}(
					$this->subject, $form, $request
				);
			} else
				$mav = ModelAndView::create();
			
			return parent::postHandleRequest($mav, $request);
		}
		
		public function doDrop(
			Prototyped $subject, Form $form, HttpRequest $request
		)
		{
			return DropCommand::create()->run($subject, $form, $request);
		}
		
		public function doSave(
			Prototyped $subject, Form $form, HttpRequest $request
		)
		{
			return SaveCommand::create()->run($subject, $form, $request);
		}
		
		public function doEdit(
			Prototyped $subject, Form $form, HttpRequest $request
		)
		{
			return EditCommand::create()->run($subject, $form, $request);
		}
		
		public function doAdd(
			Prototyped $subject, Form $form, HttpRequest $request
		)
		{
			return AddCommand::create()->run($subject, $form, $request);
		}
	}
?>