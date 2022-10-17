<?php
class ControllerExtensionModuleTickets extends Controller {
	private $error = array();
	
	public function install() {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "customer_to_product` (
			`case_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`case_title` varchar(99) NOT NULL,
			`description` text NOT NULL,
			`type` varchar(999) NOT NULL,
			`status` tinyint(1) NOT NULL,
			`date_added` datetime NOT NULL,
			`date_modified` datetime NOT NULL,
			`assignee_id` int(11) NOT NULL,
			`order_id` int(11) NOT NULL,
			`customer_id` int(11) NOT NULL,
			`language_id` int(11) NOT NULL,
			`image` varchar(999) NOT NULL
			)");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "tickets_to_comment` (
			`comment_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`case_id` int(11) NOT NULL,
			`customer_id` int(11) NOT NULL,
			`user_id` int(11) NOT NULL,
			`comment_body` varchar(999) NOT NULL,
			`date_added` datetime NOT NULL,
			`image` varchar(999) NOT NULL
			)");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "user_to_agent` (
			`agent_id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
			`user_id` int(11) NOT NULL UNIQUE KEY
			)");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "tickets_to_agent` (
			`agent_id` int(11) NOT NULL,
  			`case_id` int(11) NOT NULL UNIQUE KEY
			)");
	}

	public function uninstall(){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_to_product` ");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_comment` ");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "user_to_agent` ");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_agent` ");
	}

	public function index() {
		$this->install();

		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');

		$this->load->model('extension/module/tickets');

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

			$this->model_setting_setting->editSetting('module_tickets', $this->request->post);
			
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['action'] = $this->url->link('extension/module/tickets', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		
		if (isset($this->request->post['module_tickets_status'])) {
			$data['module_tickets_status'] = $this->request->post['module_tickets_status'];
		} else {
			$data['module_tickets_status'] = $this->config->get('module_tickets_status');
		}

		if (isset($this->request->post['module_tickets_support_email'])) {
			$data['module_tickets_support_email'] = $this->request->post['module_tickets_support_email'];
		} else {
			$data['module_tickets_support_email'] = $this->config->get('module_tickets_support_email');
		}
		
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/tickets', $data));

	}

	public function add() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			// image upload
			
			if($_FILES['image']['name'] != null){
				
				$image_file =$_FILES['image'];
				$image_name =$_FILES['image']['name'];
				$image_tmp_name =$_FILES['image']['tmp_name'];
					
				$image_tempExtension = explode('.',$image_name);
				$image_fileExtension = strtolower(end($image_tempExtension));
				$image_fileName = $image_tempExtension[0];

				$isAllowed = array('jpg','png','pdf');
				if($_FILES['image']['size'] <= 5000000 ){
					if(in_array($image_fileExtension,$isAllowed)){
						$newImageFileName = $image_fileName . "." . $image_fileExtension;
						$path = DIR_IMAGE . "ticketImage/";
						if (!file_exists($path)){
							mkdir($path,0777,true);
						}
						$imageFileDestination = $path . $newImageFileName;
						$this->request->post['image'] = "ticketImage/".$newImageFileName;
						move_uploaded_file($image_tmp_name,$imageFileDestination);
					}else{
						$this->response->redirect($this->url->link('extension/module/tickets/add', 'user_token=' .  $this->session->data['user_token'] . '&error_image=' . $this->language->get('error_image'), true));
					}
				}else{
					$this->response->redirect($this->url->link('extension/module/tickets/add', 'user_token=' .  $this->session->data['user_token'] . '&error_image=' . $this->language->get('error_image_size'), true));
				}
			}else{
				$this->request->post['image'] = '';
			}
			
			$case = $this->model_extension_module_tickets->addCase($this->request->post);

			$this->ticketsToAgent($case);

			// email system
			$this->load->model('setting/setting');

			$case_info = $this->model_extension_module_tickets->getCase($case);

			if ($this->request->server['HTTPS']) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}
			$data['base'] = HTTPS_CATALOG;
			$data['store_name'] = $this->config->get('config_name');
			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
			} else {
				$data['logo'] = '';
			}

			$data['customer_id'] = $case_info['customer_id'];

			$data['link'] = $data['base'] . 'index.php?route=extension/module/tickets/view&case_id='.$case;
		
			$from = $this->config->get('module_tickets_support_email');

			if (!$from) {
				$from = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

				if (!$from) {
					$from = $this->config->get('config_email');
				}
			}

			$to = $this->model_extension_module_tickets->getCustomerEmail($case_info['customer_id']);

			$adminName = $this->config->get('config_owner');
		
			$data['title'] = "New Ticket";
			$data['case_title'] = $this->request->post['case_title'];
			$data['description'] = utf8_substr(strip_tags(html_entity_decode($this->request->post['description'], ENT_QUOTES, 'UTF-8')), 0,50) . '....';
			$data['order_id'] = $this->request->post['order_id'];
			
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($adminName, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($data['title'], $this->request->post['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('extension/module/ticket_mail', $data));
			$mail->send();

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			
			
			$this->response->redirect($this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $case_id) {
				$this->model_extension_module_tickets->deleteCase($case_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			if (isset($this->request->get['filter_customer_id'])) {
				$filter_customer_id = $this->request->get['filter_customer_id'];
			} else {
				$filter_customer_id = '';
			}
	
			if (isset($this->request->get['filter_date_added'])) {
				$filter_date_added = $this->request->get['filter_date_added'];
			} else {
				$filter_date_added = '';
			}
	
			if (isset($this->request->get['filter_status'])) {
				$filter_status = $this->request->get['filter_status'];
			} else {
				$filter_status = '';
			}
	
			if (isset($this->request->get['sort'])) {
				$sort = $this->request->get['sort'];
			} else {
				$sort = 'title';
			}
	
			if (isset($this->request->get['order'])) {
				$order = $this->request->get['order'];
			} else {
				$order = 'ASC';
			}
	
			if (isset($this->request->get['page'])) {
				$page = (int)$this->request->get['page'];
			} else {
				$page = 1;
			}
	
			$url = '';
	
			if (isset($this->request->get['filter_customer_id'])) {
				$url .= '&filter_customer_id=' . $this->request->get['filter_customer_id'];
			}
	
			if (isset($this->request->get['filter_date_added'])) {
				$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
			}
	
			if (isset($this->request->get['filter_status'])) {
				$url .= '&filter_status=' . $this->request->get['filter_status'];
			}
	
			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}
	
			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}
	
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

	}

	public function getList() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/module/tickets');

		if (isset($this->request->get['filter_customer_id'])) {
			$filter_customer_id = $this->request->get['filter_customer_id'];
		} else {
			$filter_customer_id = '';
		}

		if (isset($this->request->get['filter_date_start'])) {
			$filter_date_start = $this->request->get['filter_date_start'];
		} else {
			$filter_date_start = '';
		}

		if (isset($this->request->get['filter_date_end'])) {
			$filter_date_end = $this->request->get['filter_date_end'];
		} else {
			$filter_date_end = '';
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		if (isset($this->request->get['filter_agent_id'])) {
			$filter_agent_id = $this->request->get['filter_agent_id'];
		} else {
			$filter_agent_id = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'title';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$agent_group = $this->model_extension_module_tickets->agentToUserGroup($this->user->getId());
		
		if (!empty($agent_group)) {
			$filter_user_id = '';
		} else {
			$filter_user_id = $this->user->getId();
		}

		$url = '';

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . $this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('extension/module/tickets/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		
		$data['delete'] = $this->url->link('extension/module/tickets/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['cases'] = array();

		$filter_data = array(
			'filter_customer_id'	=> $filter_customer_id,
			'filter_date_start'	  	=> $filter_date_start,
			'filter_date_end'	  	=> $filter_date_end,
			'filter_status'	  		=> $filter_status,
			'filter_user_id'		=> $filter_user_id,
			'filter_agent_id'		=> $filter_agent_id,
			'sort'  				=> 'date_added',
			'order' 				=> 'DESC',
			'start' 				=> ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' 				=> $this->config->get('config_limit_admin')
		);
		
		$case_total = $this->model_extension_module_tickets->getTotalCases($filter_user_id);

		$results = $this->model_extension_module_tickets->getCases($filter_data);

		foreach ($results as $result) {
			$data['cases'][] = array(
				'case_id'      => $result['case_id'],
				'case_title'      => $result['case_title'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0,50) . '....',
				'status' => $result['status'] ? $this->language->get('text_open') : $this->language->get('text_close'),
				'firstname'      => $result['firstname'],
				'lastname'      => $result['lastname'],
				'agent_name'      => $result['agent_name'],
				'agent_url'   => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&filter_agent_id=' . $result['agent_id'], true),
				'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'date_modified'  => date($this->language->get('date_format_short'), strtotime($result['date_modified'])),
				'customer_href'	=> $this->url->link('customer/customer/edit', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'], true),
				'view'       => $this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $result['case_id']. '&customer_id=' . $result['customer_id'] . $url, true),
				'ticket_assignee'       => $this->url->link('extension/module/tickets/ticketAssigningForm', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $result['case_id'] . $url, true)
			);
		}
		

		$data['commentByCases'] = array();

		$commentByCases = $this->model_extension_module_tickets->getCommentsByCase($filter_data);

		foreach ($commentByCases as $commentByCase) {

			$data['commentByCases'][] = array(
				'case_title'      => $commentByCase['case_title'],
				'comment_body' => utf8_substr(strip_tags(html_entity_decode($commentByCase['comment_body'], ENT_QUOTES, 'UTF-8')), 0,50) . '..',
				'view'       => $this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $commentByCase['case_id']. '&customer_id=' . $commentByCase['customer_id'] . $url, true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_title'] = $this->url->link('extension/module/tickets', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $case_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($case_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($case_total - $this->config->get('config_limit_admin'))) ? $case_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $case_total, ceil($case_total / $this->config->get('config_limit_admin')));

		$data['user_token'] = $this->session->data['user_token'];
		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['filter_user_id'] = $filter_user_id;
		$data['isAgent'] = $this->model_extension_module_tickets->isAgent($this->user->getId());
		if (empty($data['filter_user_id']) || !empty($data['isAgent']) ) {
			$data['agentOrAdmin'] = '1';
		}else {
			$data['agentOrAdmin'] = '0';
		}
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/case_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['case_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['case_title'])) {
			$data['error_case_title'] = $this->error['case_title'];
		} else {
			$data['error_case_title'] = array();
		}

		if (isset($this->error['description'])) {
			$data['error_description'] = $this->error['description'];
		} else {
			$data['error_description'] = array();
		}

		if (isset($this->error['customer_id'])) {
			$data['error_customer_id'] = $this->error['customer_id'];
		} else {
			$data['error_customer_id'] = '';
		}

		if (isset($this->error['order_id'])) {
			$data['error_order_id'] = $this->error['order_id'];
		} else {
			$data['error_order_id'] = '';
		}

		if (isset($this->request->get['error_image'])) {
			$data['error_image'] = $this->request->get['error_image'];
		} else {
			$data['error_image'] = array();
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (!isset($this->request->get['case_id'])) {
			$data['action'] = $this->url->link('extension/module/tickets/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/tickets/edit', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $this->request->get['case_id'] . $url, true);
		}

		$data['cancel'] = $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true);

		if (isset($this->request->get['case_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$case_info = $this->model_extension_module_tickets->getCase($this->request->get['case_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['case_title'])) {
			$data['case_title'] = $this->request->post['case_title'];
		} elseif (!empty($case_info)) {
			$data['case_title'] = $case_info['case_title'];
		} else {
			$data['case_title'] = '';
		}

		if (isset($this->request->post['description'])) {
			$data['description'] = $this->request->post['description'];
		} elseif (!empty($case_info)) {
			$data['description'] = $case_info['description'];
		} else {
			$data['description'] = '';
		}

		if (isset($this->request->post['customer_id'])) {
			$data['customer_id'] = $this->request->post['customer_id'];
		} elseif (!empty($case_info)) {
			$data['customer_id'] = $case_info['customer_id'];
		} else {
			$data['customer_id'] = '';
		}

		if (isset($this->request->post['order_id'])) {
			$data['order_id'] = $this->request->post['order_id'];
		} elseif (!empty($case_info)) {
			$data['order_id'] = $case_info['order_id'];
		} else {
			$data['order_id'] = '';
		}

		$data['orders'] = array();

		$results = $this->model_extension_module_tickets->getOrdersDetail($data['customer_id']);
		foreach ($results as $result) {
			$data['orders'][] = array(
				'order_id'      => $result['order_id'],
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'total'      => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value'])
			);
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($product_info)) {
			$data['status'] = $product_info['status'];
		} else {
			$data['status'] = true;
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/case_form', $data));
	}

	protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'extension/module/tickets')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['case_title']) < 1) || (utf8_strlen($this->request->post['case_title']) > 64)) {
			$this->error['case_title'] = $this->language->get('error_case_title');
		}
		
		if (utf8_strlen($this->request->post['description']) < 3) {
			$this->error['description'] = $this->language->get('error_description');
		}

		if (utf8_strlen($this->request->post['customer_id']) == null ) {
			$this->error['customer_id'] = $this->language->get('error_customer_id');
		}

		if (utf8_strlen($this->request->post['order_id']) == null ) {
			$this->error['order_id'] = $this->language->get('error_order_id');
		}

		return !$this->error;
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/tickets')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_order'])) {
			$this->load->model('extension/module/tickets');

			if (isset($this->request->get['filter_order'])) {
				$filter_order = $this->request->get['filter_order'];
			} else {
				$filter_order = '';
			}

			$results = $this->model_extension_module_tickets->getOrdersDetail($filter_order);
			foreach ($results as $result) {
				$json[] = array(
					'order_id' 			=> $result['order_id']
				);
			}
		}

		if (isset($this->request->get['filter_customer'])) {
			$this->load->model('extension/module/tickets');

			if (isset($this->request->get['filter_customer'])) {
				$filter_customer = $this->request->get['filter_customer'];
			} else {
				$filter_customer = '';
			}

			$filter_data = array(
				'filter_customer' => $this->request->get['filter_customer'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_extension_module_tickets->getCustomers($filter_data);
			foreach ($results as $result) {
				$json[] = array(
					'customer_id' 	=> $result['customer_id'],
					'name' 			=> $result['firstname'] . ' ' . $result['lastname']
				);
			}
		}

		if (isset($this->request->get['filter_case'])) {
			$this->load->model('extension/module/tickets');

			if (isset($this->request->get['filter_case'])) {
				$filter_case = $this->request->get['filter_case'];
			} else {
				$filter_case = '';
			}

			$results = $this->model_extension_module_tickets->getTickets($filter_case);
			foreach ($results as $result) {
				$json[] = array(
					'case_id' 		=> $result['case_id'],
					'case_title' 	=> $result['case_title']
				);
			}
		}

		if (isset($this->request->get['filter_agent'])) {
			$this->load->model('extension/module/tickets');

			if (isset($this->request->get['filter_agent'])) {
				$filter_agent = $this->request->get['filter_agent'];
			} else {
				$filter_agent = '';
			}

			$filter_data = array(
				'filter_agent' => $this->request->get['filter_agent'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_extension_module_tickets->getAgents($filter_data);
			foreach ($results as $result) {
				$json[] = array(
					'agent_id' 	 => $result['agent_id'],
					'agent_name' => $result['agent_name']
				);
			}
		}

		if (isset($this->request->get['filter_user'])) {
			$this->load->model('extension/module/tickets');

			if (isset($this->request->get['filter_user'])) {
				$filter_user = $this->request->get['filter_user'];
			} else {
				$filter_user = '';
			}

			$filter_data = array(
				'filter_user' => $this->request->get['filter_user'],
			);

			$results = $this->model_extension_module_tickets->getUsers($filter_data);
			foreach ($results as $result) {
				$json[] = array(
					'user_id' 	 => $result['user_id'],
					'user_name' => $result['username']
				);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function view() {
		
		$this->load->language('extension/module/tickets');

		$this->load->model('extension/module/tickets');

		if (isset($this->request->get['case_id'])) {
			$data['case_id'] = $this->request->get['case_id'];
		} else {
			$data['case_id'] = 0;
		}

		$url = '';

		if (isset($this->request->get['case_id'])) {
			$url .= '&case_id=' . $this->request->get['case_id'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$case_info = $this->model_extension_module_tickets->getCase($data['case_id']);

		if ($case_info) {
			$this->document->setTitle($this->language->get('text_view'));

			$data['case_heading'] = $case_info['case_title'];

			$data['case_status'] = $case_info['status'];

			$data['agent_name'] = $this->model_extension_module_tickets->getAgentByTicket($data['case_id']);
			
			$data['description'] = html_entity_decode($case_info['description'], ENT_QUOTES, 'UTF-8');
			$this->load->model('tool/image');
			
			if ($case_info['image']) {
				$data['image'] = HTTP_CATALOG.'image/'.$case_info['image'];
			}

			$data['order_data']	= $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] .'&order_id=' . $case_info['order_id'], true);
			
			$data['all_tickets'] = $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&filter_customer_id=' . $case_info['customer_id'], true);
		}
		
	    //Comment

		if (isset($this->request->get['customer_id'])) {
			$data['customer_id'] = $this->request->get['customer_id'];
		} else {
			$data['customer_id'] = 0;
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->request->post['status']==1) {
			
			$this->model_extension_module_tickets->ticketDateModified($this->request->post['case_id']);
			
			// image uplode
			
			if($_FILES['image']['name'] != null){
				
				$image_file =$_FILES['image'];
				$image_name =$_FILES['image']['name'];
				$image_tmp_name =$_FILES['image']['tmp_name'];
					
				$image_tempExtension = explode('.',$image_name);
				$image_fileExtension = strtolower(end($image_tempExtension));
				$image_fileName = $image_tempExtension[0];

				$isAllowed = array('jpg','png','pdf');

				if($_FILES['image']['size'] <= 5000000 ){
					if(in_array($image_fileExtension,$isAllowed)){
						$newImageFileName = $image_fileName . "." . $image_fileExtension;
						$path = DIR_IMAGE . "ticketImage/";
						if (!file_exists($path)){
							mkdir($path,0777,true);
						}
						$imageFileDestination = $path . $newImageFileName;
						$this->request->post['image'] = "ticketImage/".$newImageFileName;
						move_uploaded_file($image_tmp_name,$imageFileDestination);
					}else{
						$this->response->redirect($this->url->link('extension/module/tickets/add', 'user_token=' .  $this->session->data['user_token'] . '&error_image=' . $this->language->get('error_image'), true));
					}
				}else{
					$this->response->redirect($this->url->link('extension/module/tickets/add', 'user_token=' .  $this->session->data['user_token'] . '&error_image=' . $this->language->get('error_image_size'), true));
				}
			}else{
				$this->request->post['image'] = '';
			}

			if(!($this->request->post['comment_body'])){
				$this->response->redirect($this->url->link('extension/module/tickets/view','user_token=' .  $this->session->data['user_token'] . '&error_comment=' . $this->language->get('error_comment').'&case_id='.$this->request->post['case_id'], true));
			}
			
			$this->model_extension_module_tickets->addComment($this->request->post);
			// email 
			$this->load->model('setting/setting');

			$case_info = $this->model_extension_module_tickets->getCase($this->request->post['case_id']);

			if ($case_info) {
				$this->document->setTitle($this->language->get('text_view'));

				$data['case_title'] = $case_info['case_title'];
			}

			$data['title'] = "New Comment";
			$data['comment_body'] = $this->request->post['comment_body'];
			$data['case_id'] = $this->request->post['case_id'];
			if ($this->request->server['HTTPS']) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}
			$data['base'] = HTTPS_CATALOG;
			$data['store_name'] = $this->config->get('config_name');
			
			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
			} else {
				$data['logo'] = '';
			}
	
			$data['customer_id'] = $case_info['customer_id'];

			$data['link'] = $data['base'] . 'index.php?route=extension/module/tickets/view&case_id='.$this->request->post['case_id'];
		
			$from = $this->config->get('module_tickets_support_email');

			if (!$from) {
				$from = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

				if (!$from) {
					$from = $this->config->get('config_email');
				}
			}

			$to = $this->model_extension_module_tickets->getCustomerEmail($case_info['customer_id']);

			$adminName = $this->config->get('config_owner');
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($adminName, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($data['title'], $this->request->post['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('extension/module/ticket_mail', $data));
			$mail->send();

			$this->response->redirect($this->url->link('extension/module/tickets/view', 'user_token=' .  $this->session->data['user_token'] . '&case_id=' . $this->request->post['case_id'], true));
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->request->post['status']==0) {
			
			$this->model_extension_module_tickets->ticketDateModified($this->request->post['case_id']);
			$this->model_extension_module_tickets->closeCase($this->request->post);
			$this->model_extension_module_tickets->addComment($this->request->post);
				
			// email

			$this->load->model('setting/setting');

			$case_info = $this->model_extension_module_tickets->getCase($this->request->post['case_id']);

			if ($case_info) {
				$this->document->setTitle($this->language->get('text_view'));

				$data['case_title'] = $case_info['case_title'];
			}

			$data['title'] = "New Comment";
			$data['comment_body'] = $this->request->post['comment_body'];
			$data['case_id'] = $this->request->post['case_id'];
			$data['closing_comment'] = $this->language->get('text_view');
			if ($this->request->server['HTTPS']) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}
			$data['base'] = HTTPS_CATALOG;
			$data['store_name'] = $this->config->get('config_name');
			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
			} else {
				$data['logo'] = '';
			}
	
			$data['customer_id'] = $this->customer->getId();

			$data['link'] = $data['base'] . 'index.php?route=extension/module/tickets/view&case_id='.$this->request->post['case_id'];
		
			$from = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

			$from = $this->config->get('module_tickets_support_email');

			if (!$from) {
				$from = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

				if (!$from) {
					$from = $this->config->get('config_email');
				}
			}

			$to = $this->model_extension_module_tickets->getCustomerEmail($case_info['customer_id']);

			$adminName = $this->config->get('config_owner');
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($from);
			$mail->setSender(html_entity_decode($adminName, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($data['title'], $this->request->post['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('extension/module/ticket_mail', $data));
			$mail->send();
			
			$this->response->redirect($this->url->link('extension/module/tickets/getList', 'user_token=' .  $this->session->data['user_token'] . '', true));
		}

		$data['action'] = $this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'], true);
		
		if (isset($this->request->get['error_image'])) {
			$data['error_image'] = $this->request->get['error_image'];
		} else {
			$data['error_image'] = array();
		}

		if (isset($this->request->get['error_comment'])) {
			$data['error_comment'] = $this->request->get['error_comment'];
		} else {
			$data['error_comment'] = array();
		}
		
		$data['comments'] = array();
		
		$results = $this->model_extension_module_tickets->getComments($data['case_id']);

		foreach ($results as $result) {
			$data['comments'][] = array(
				'customer_id'      => $result['customer_id'],
				'user_id'      => $result['user_id'],
				'date_added'  => $result['date_added'],
				'image' =>  $result['image'] ? HTTP_CATALOG.'image/'.$result['image'] : null,
				'comment_body' => html_entity_decode($result['comment_body'], ENT_QUOTES, 'UTF-8'),
				'delete_href'	=> $this->url->link('extension/module/tickets/deleteComment', 'user_token=' . $this->session->data['user_token'] .'&comment_id=' . $result['comment_id'] . $url, true)
			);
		}

		// Ticket Assigning to Agent
		
		$data['agents'] = array();
	
		$agents = $this->model_extension_module_tickets->getAgentAndUser();

		foreach ($agents as $agent) {
			$data['agents'][] = array(
				'agent_id'      => $agent['agent_id'],
				'agent_name'      => $agent['username']
				);
		}

		$data['actionForAssigning'] = $this->url->link('extension/module/tickets/ticketAssigningForm', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/case_view', $data));
	}

	public function deleteComment() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (isset($this->request->get['comment_id'])) {
			$comment_id = $this->request->get['comment_id'];
		} else {
			$comment_id = 0;
		}

		$this->model_extension_module_tickets->deleteComment($comment_id);

		if (isset($this->request->get['case_id'])) {
			$data['case_id'] = $this->request->get['case_id'];
		} else {
			$data['case_id'] = 0;
		}

		$this->model_extension_module_tickets->ticketDateModified($data['case_id']);

		if (isset($this->request->get['customer_id'])) {
			$data['customer_id'] = $this->request->get['customer_id'];
		} else {
			$data['customer_id'] = 0;
		}

		$url = '';
	
		if (isset($this->request->get['case_id'])) {
			$url .= '&case_id=' . $this->request->get['case_id'];
		}

		if (isset($this->request->get['customer_id'])) {
			$url .= '&customer_id=' . $this->request->get['customer_id'];
		}

		$this->response->redirect($this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'] . $url, true));

	}

	public function editCaseStatus() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (isset($this->request->get['case_id'])) {
			$case_id = $this->request->get['case_id'];
		} else {
			$case_id = '';
		}

		if (isset($this->request->get['status'])) {
			$status = $this->request->get['status'];
		} else {
			$status = '';
		}
		
		if (isset($this->request->get['filter_customer_id'])) {
			$filter_customer_id = $this->request->get['filter_customer_id'];
		} else {
			$filter_customer_id = '';
		}

		if (isset($this->request->get['filter_date_added'])) {
			$filter_date_added = $this->request->get['filter_date_added'];
		} else {
			$filter_date_added = '';
		}

		if (isset($this->request->get['filter_status'])) {
			$filter_status = $this->request->get['filter_status'];
		} else {
			$filter_status = '';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'title';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_customer_id'])) {
			$url .= '&filter_customer_id=' . $this->request->get['filter_customer_id'];
		}

		if (isset($this->request->get['filter_date_added'])) {
			$url .= '&filter_date_added=' . $this->request->get['filter_date_added'];
		}

		if (isset($this->request->get['filter_status'])) {
			$url .= '&filter_status=' . $this->request->get['filter_status'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$this->model_extension_module_tickets->editCaseStatus($case_id,$status);

		$this->response->redirect($this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true));

	}

	public function ticketAssigningForm() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('text_assignee_form'));

		$this->load->model('extension/module/tickets');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->assigningValidationForm()) {
			
			$this->model_extension_module_tickets->ticketDateModified($this->request->post['case_id']);
			$this->model_extension_module_tickets->ticketAssigning($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$data['text_form'] = $this->language->get('text_add');

		if (isset($this->error['agent_id'])) {
			$data['error_agent_id'] = $this->error['agent_id'];
		} else {
			$data['error_agent_id'] = '';
		}

		$url = '';

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		if (isset($this->request->get['case_id'])) {
			$data['case_id'] = $this->request->get['case_id'];
		}else{
			$data['case_id'] = '';
		}

		$data['action'] = $this->url->link('extension/module/tickets/ticketAssigningForm', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['cancel'] = $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ticketAssigningForm', $data));
	}

	protected function assigningValidationForm() {

		if (utf8_strlen($this->request->post['agent_id']) == 0 ) {
			$this->error['agent_id'] = $this->language->get('error_agent_id');
		}

		return !$this->error;
	}

	protected function ticketsToAgent($case) {

		$this->load->model('extension/module/tickets');

		$agents = $this->model_extension_module_tickets->getAgentAndUser();
			
		foreach ($agents as $agent) {
				
			if($agent['user_id'] == $this->user->getId()){
					
				$this->request->post['agent_id']= $agent['agent_id'];
				$this->request->post['case_id']= $case;
					
				$this->model_extension_module_tickets->ticketAssigning($this->request->post);
			}
		}
			
	}

	public function getAgentList() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/module/tickets');
		
		$agent_group = $this->model_extension_module_tickets->agentToUserGroup($this->user->getId());
		
		if (!empty($agent_group)) {
			$filter_user_id = '';
		} else {
			$filter_user_id = $this->user->getId();
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'title';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['add'] = $this->url->link('extension/module/tickets/getAgentForm', 'user_token=' . $this->session->data['user_token'] . $url, true);
		
		$data['delete'] = $this->url->link('extension/module/tickets/deleteAgent', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['agents'] = array();

		$filter_data = array(
			'start' 				=> ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' 				=> $this->config->get('config_limit_admin')
		);
		
		$agent_total = $this->model_extension_module_tickets->getTotalAgent();

		$results = $this->model_extension_module_tickets->getAgents($filter_data);

		foreach ($results as $result) {
			$data['agents'][] = array(
				'agent_id'      => $result['agent_id'],
				'agent_name'      => $result['agent_name'],
				'agent_url'   => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&filter_agent_id=' . $result['agent_id'], true)
			);
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array)$this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}

		$url = '';

		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['sort_title'] = $this->url->link('extension/module/tickets', 'user_token=' . $this->session->data['user_token'] . '&sort=title' . $url, true);

		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $agent_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('extension/module/tickets/getAgentList', 'user_token=' . $this->session->data['user_token'] . '&page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($agent_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($agent_total - 10)) ? $agent_total : ((($page - 1) * 10) + 10), $agent_total, ceil($agent_total / 10));

		$data['user_token'] = $this->session->data['user_token'];
		$data['sort'] = $sort;
		$data['order'] = $order;
		$data['filter_user_id'] = $filter_user_id;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/agent_list', $data));
	}

	public function getAgentForm() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {

			$this->model_extension_module_tickets->addAgent($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$url = '';

			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}

			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/tickets/getAgentList', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$data['text_form'] = $this->language->get('text_add_agent');
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['user_name'])) {
			$data['error_user_name'] = $this->error['user_name'];
		} else {
			$data['error_user_name'] = array();
		}


		$url = '';

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$data['action'] = $this->url->link('extension/module/tickets/getAgentForm', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['cancel'] = $this->url->link('extension/module/tickets/getAgentList', 'user_token=' . $this->session->data['user_token'] . $url, true);

		$data['user_token'] = $this->session->data['user_token'];

		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		if (isset($this->request->post['user_id'])) {
			$data['user_id'] = $this->request->post['user_id'];
		} elseif (!empty($case_info)) {
			$data['user_id'] = $case_info['user_id'];
		} else {
			$data['user_id'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/agent_form', $data));
	}

	public function deleteAgent() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (isset($this->request->post['selected'])) {
			foreach ($this->request->post['selected'] as $agent_id) {
				$this->model_extension_module_tickets->deleteAgent($agent_id);
			}

			$this->session->data['success'] = $this->language->get('text_success');

	
			if (isset($this->request->get['sort'])) {
				$sort = $this->request->get['sort'];
			} else {
				$sort = 'title';
			}
	
			if (isset($this->request->get['order'])) {
				$order = $this->request->get['order'];
			} else {
				$order = 'ASC';
			}
	
			if (isset($this->request->get['page'])) {
				$page = (int)$this->request->get['page'];
			} else {
				$page = 1;
			}
	
			$url = '';
	
			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}
	
			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}
	
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$this->response->redirect($this->url->link('extension/module/tickets/getAgentList', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

	}

	public function ticketDashboard() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/module/tickets');
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_ticket_dashboard'),
			'href' => $this->url->link('extension/module/tickets/ticketDashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$agent_group = $this->model_extension_module_tickets->agentToUserGroup($this->user->getId());
		
		if (!empty($agent_group)) {
			$filter_user_id = '';
		} else {
			$filter_user_id = $this->user->getId();
		}

		$filter_data = array(
			'filter_user_id'		=> $filter_user_id,
			'start' 				=> 0,
			'limit' 				=> 6
		);

		$data['case_total'] = $this->model_extension_module_tickets->getTotalCases($filter_user_id);
		$data['all_tickets'] = $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] , true);
		
		$data['agent_total'] = $this->model_extension_module_tickets->getTotalAgent();
		$data['all_agents'] = $this->url->link('extension/module/tickets/getAgentList', 'user_token=' . $this->session->data['user_token'] , true);
		
		$data['cases'] = array();
		
		$results = $this->model_extension_module_tickets->getCases($filter_data);

		foreach ($results as $result) {
			$data['cases'][] = array(
				'case_id'      => $result['case_id'],
				'case_title'      => $result['case_title'],
				'status' => $result['status'] ? $this->language->get('text_open') : $this->language->get('text_close'),
				'agent_name'      => $result['agent_name'],
				'agent_url'   => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&filter_agent_id=' . $result['agent_id'], true),
				'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'view'       => $this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $result['case_id']. '&customer_id=' . $result['customer_id'] , true)
			);
		}

		$data['agentsTickets'] = array();
		
		$agentsTickets = $this->model_extension_module_tickets->getAgentsTicktsDetail();

		foreach ($agentsTickets as $agentsTicket) {
			$data['agentsTickets'][] = array(
				'agent_name'      => $agentsTicket['agent_name'],
				'agent_url'   => $this->url->link('extension/module/tickets/getList', 'user_token=' . $this->session->data['user_token'] . '&filter_agent_id=' . $agentsTicket['agent_id'], true),
				'open_ticket'      => $agentsTicket['open_ticket'],
				'close_ticket'      => $agentsTicket['close_ticket'],
			);
		}

		$data['commentByCases'] = array();

		$commentByCases = $this->model_extension_module_tickets->getCommentsByCase($filter_data);

		foreach ($commentByCases as $commentByCase) {

			$data['commentByCases'][] = array(
				'case_title'      => $commentByCase['case_title'],
				'comment_body' => utf8_substr(strip_tags(html_entity_decode($commentByCase['comment_body'], ENT_QUOTES, 'UTF-8')), 0,500),
				'view'       => $this->url->link('extension/module/tickets/view', 'user_token=' . $this->session->data['user_token'] . '&case_id=' . $commentByCase['case_id']. '&customer_id=' . $commentByCase['customer_id'], true)
			);
		}

		$data['filter_user_id'] = $filter_user_id;
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/ticket_dashboard', $data));

	}
}