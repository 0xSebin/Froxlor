<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * For the full copyright and license information, please view the COPYING
 * file that was distributed with this source code. You can also view the
 * COPYING file online at http://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  (c) the authors
 * @author     Froxlor team <team@froxlor.org> (2010-)
 * @license    GPLv2 http://files.froxlor.org/misc/COPYING.txt
 * @package    Formfields
 *
 */
return array(
	'messages_add' => array(
		'title' => $lng['admin']['message'],
		'image' => 'fa-solid fa-paper-plane',
		'sections' => array(
			'section_a' => array(
				'fields' => array(
					'recipient' => array(
						'label' => $lng['admin']['recipient'],
						'type' => 'select',
						'select_var' => $recipients,
						'selected' => 1
					),
					'subject' => array(
						'label' => $lng['admin']['subject'],
						'type' => 'text',
						'value' => $lng['admin']['nosubject'],
						'mandatory' => true
					),
					'message' => array(
						'label' => $lng['admin']['text'],
						'type' => 'textarea',
						'mandatory' => true
					)
				)
			)
		)
	)
);
