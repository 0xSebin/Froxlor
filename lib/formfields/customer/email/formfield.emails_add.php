<?php

/**
 * This file is part of the Froxlor project.
 * Copyright (c) 2010 the Froxlor Team (see authors).
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, you can also view it online at
 * https://files.froxlor.org/misc/COPYING.txt
 *
 * @copyright  the authors
 * @author     Froxlor team <team@froxlor.org>
 * @license    https://files.froxlor.org/misc/COPYING.txt GPLv2
 */

return [
	'emails_add' => [
		'title' => lng('emails.emails_add'),
		'image' => 'fa-solid fa-plus',
		'self_overview' => ['section' => 'email', 'page' => 'email_domain', 'domainid' => $email_domainid ?: 0],
		'sections' => [
			'section_a' => [
				'title' => lng('emails.emails_add'),
				'image' => 'icons/email_add.png',
				'fields' => [
					'email_part' => [
						'label' => lng('emails.emailaddress'),
						'type' => 'text',
						'next_to' => [
							'domain' => [
								'next_to_prefix' => '@',
								'type' => 'select',
								'select_var' => $domains
							]
						]
					],
					'iscatchall' => [
						'label' => lng('emails.iscatchall'),
						'type' => 'checkbox',
						'value' => '1',
						'checked' => false
					]
				]
			]
		]
	]
];
