<?php
class ControllerExtensionModuleTickets extends Controller {
	private $error = array();
	
	public function index() {

		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('extension/module/tickets');

		$this->getList();
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
						$this->response->redirect($this->url->link('extension/module/tickets/add', 'error_image=' . $this->language->get('error_image'), true));
					}
				}else{
					$this->response->redirect($this->url->link('extension/module/tickets/add', 'error_image=' . $this->language->get('error_image_size'), true));
				}
			}else{
				$this->request->post['image'] = '';
			}

			$case = $this->model_extension_module_tickets->addCase($this->request->post);

			$this->load->model('setting/setting');

			if ($this->request->server['HTTPS']) {
				$server = $this->config->get('config_ssl');
			} else {
				$server = $this->config->get('config_url');
			}
			$data['base'] = $server;
			$data['store_name'] = $this->config->get('config_name');
	
			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
			} else {
				$data['logo'] = '';
			}
	
			$data['customer_id'] = $this->customer->getId();
	
			$data['link'] = $data['base'] . 'admin/index.php?route=extension/module/tickets/view&case_id='.$case.'&customer_id='. $this->customer->getId();
		
			$data['title'] = "New Ticket";
			$data['case_title'] = $this->request->post['case_title'];
			$data['description'] = utf8_substr(strip_tags(html_entity_decode($this->request->post['description'], ENT_QUOTES, 'UTF-8')), 0,50) . '....';;
			$data['order_id'] = $this->request->post['order_id'];
			
			$to = $this->config->get('module_tickets_support_email');

			if (!$to) {
				$to = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

				if (!$to) {
					$to = $this->config->get('config_email');
				}
			}

			$customerName = $this->customer->getfirstName() . ' '.  $this->customer->getlastName();
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($this->customer->getemail());
			$mail->setSender(html_entity_decode($customerName, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($data['title'], $this->request->post['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('extension/module/ticket_mail', $data));
			$mail->send();

			$this->response->redirect($this->url->link('extension/module/tickets', '', true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('extension/module/tickets');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('extension/module/tickets');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_extension_module_tickets->editCase($this->request->get['case_id'], $this->request->post);

			$this->response->redirect($this->url->link('extension/module/tickets', '', true));
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

			$this->response->redirect($this->url->link('extension/module/tickets', '', true));
		}

		$this->getList();
	}

	protected function getList() {

		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('extension/module/tickets', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_case_list'),
			'href' => $this->url->link('extension/module/tickets', '', true)
		);

		$data['add'] = $this->url->link('extension/module/tickets/add', '', true);
		$data['delete'] = $this->url->link('extension/module/tickets/delete', '', true);

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$data['cases'] = array();

		$filter_data = array(
			'sort'  => 'date_added',
			'order' => 'DESC',
			'start' => ($page - 1) * 10,
			'limit' => 10
		);

		$case_total = $this->model_extension_module_tickets->getTotalCases();

		$results = $this->model_extension_module_tickets->getCases($filter_data);

		foreach ($results as $result) {
			$data['cases'][] = array(
				'case_id'      => $result['case_id'],
				'case_title'      => $result['case_title'],
				'description' => utf8_substr(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8')), 0,50) . '....',
				'status' => $result['status'] ? $this->language->get('text_open') : $this->language->get('text_close'),
				'date_added'  => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'date_modified'  => date($this->language->get('date_format_short'), strtotime($result['date_modified'])),
				'view'       => $this->url->link('extension/module/tickets/view', '&case_id=' . $result['case_id'], true)
			);
		}

		$pagination = new Pagination();
		$pagination->total = $case_total;
		$pagination->page = $page;
		$pagination->limit = 10;
		$pagination->url = $this->url->link('extension/module/tickets/getList', 'page={page}', true);

		$data['pagination'] = $pagination->render();

		$data['results'] = sprintf($this->language->get('text_pagination'), ($case_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($case_total - 10)) ? $case_total : ((($page - 1) * 10) + 10), $case_total, ceil($case_total / 10));

		$data['continue'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/case_list', $data));
	}

	protected function getForm() {
		$data['text_form'] = !isset($this->request->get['case_id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		
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

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_case_form'),
			'href' => $this->url->link('extension/module/tickets', '', true)
		);

		if (!isset($this->request->get['case_id'])) {
			$data['action'] = $this->url->link('extension/module/tickets/add', '', true);
		} else {
			$data['action'] = $this->url->link('extension/module/tickets/edit', '&case_id=' . $this->request->get['case_id'], true);
		}

		$data['cancel'] = $this->url->link('extension/module/tickets', '', true);

		if (isset($this->request->get['case_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$case_info = $this->model_extension_module_tickets->getCase($this->request->get['case_id']);
		}


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


		if (isset($this->request->post['order_id'])) {
			$data['order_id'] = $this->request->post['order_id'];
		} elseif (!empty($case_info)) {
			$data['order_id'] = $case_info['order_id'];
		} else {
			$data['order_id'] = '';
		}

		$data['orders'] = array();

		$results = $this->model_extension_module_tickets->getOrders();
		// var_dump($results);die;
		foreach ($results as $result) {
			$data['orders'][] = array(
				'order_id'      => $result['order_id'],
				'date_added'      => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'total'      => $this->currency->format($result['total'], $result['currency_code'], $result['currency_value'])
			);
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('extension/module/case_form', $data));
	}

	protected function validateForm() {

		if ((utf8_strlen($this->request->post['case_title']) < 1) || (utf8_strlen($this->request->post['case_title']) > 64)) {
			$this->error['case_title'] = $this->language->get('error_case_title');
		}

		if (utf8_strlen($this->request->post['description']) < 3) {
			$this->error['description'] = $this->language->get('error_description');
		}

		if (utf8_strlen($this->request->post['order_id']) == null ) {
			$this->error['order_id'] = $this->language->get('error_order_id');
		}

		return !$this->error;
	}

	public function autocomplete() {
		$json = array();

		if (isset($this->request->get['filter_order'])) {
			$this->load->model('extension/module/tickets');

			$filter_data = array(
				'filter_order' => $this->request->get['filter_order'],
				'start'       => 0,
				'limit'       => 5
			);

			$results = $this->model_extension_module_tickets->getOrders($filter_data);
			foreach ($results as $result) {
				$json[] = array(
					'order_id' 			=> $result['order_id']
				);
			}
		}

		$sort_order = array();

		foreach ($json as $key => $value) {
			$sort_order[$key] = $value['order_id'];
		}

		array_multisort($sort_order, SORT_ASC, $json);

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
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_case_list'),
			'href' => $this->url->link('extension/module/tickets', '', true)
		);

		$case_info = $this->model_extension_module_tickets->getCase($data['case_id']);
		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}
		if ($case_info) {
			$this->document->setTitle($this->language->get('text_view'));


			$data['case_title'] = $case_info['case_title'];

			$data['case_status'] = $case_info['status'];

			$data['description'] = html_entity_decode($case_info['description'], ENT_QUOTES, 'UTF-8');
			
			$this->load->model('tool/image');
			
			if ($case_info['image']) {
				$data['image'] = $server.'image/'.$case_info['image'];
			}
		}
		
		//Comment

		if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
			
			$this->model_extension_module_tickets->ticketDateModified($this->request->post['case_id']);
			
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
						$this->response->redirect($this->url->link('extension/module/tickets/view&error_image=' . $this->language->get('error_image').'&case_id='.$this->request->post['case_id'], true));
					}
				}else{
					$this->response->redirect($this->url->link('extension/module/tickets/view&error_image=' . $this->language->get('error_image_size').'&case_id='.$this->request->post['case_id'], true));
				}
			}else{
				$this->request->post['image'] = '';
			}

			if(!($this->request->post['comment_body'])){
				$this->response->redirect($this->url->link('extension/module/tickets/view&error_comment=' . $this->language->get('error_comment').'&case_id='.$this->request->post['case_id'], true));
			}

			$this->model_extension_module_tickets->addComment($this->request->post);

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
			$data['base'] = $server;
			$data['store_name'] = $this->config->get('config_name');
	
			if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
				$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
			} else {
				$data['logo'] = '';
			}
	
			$data['customer_id'] = $this->customer->getId();
	
			$data['link'] = $data['base'] . 'admin/index.php?route=extension/module/tickets/view&case_id='.$this->request->post['case_id'].'&customer_id='. $this->customer->getId();
		
			$to = $this->config->get('module_tickets_support_email');

			if (!$to) {
				$to = $this->model_setting_setting->getSettingValue('config_email', $this->config->get('config_store_id'));

				if (!$to) {
					$to = $this->config->get('config_email');
				}
			}

			$customerName = $this->customer->getfirstName() . ' '.  $this->customer->getlastName();
			
			$mail = new Mail($this->config->get('config_mail_engine'));
			$mail->parameter = $this->config->get('config_mail_parameter');
			$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
			$mail->smtp_username = $this->config->get('config_mail_smtp_username');
			$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
			$mail->smtp_port = $this->config->get('config_mail_smtp_port');
			$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

			$mail->setTo($to);
			$mail->setFrom($this->customer->getemail());
			$mail->setSender(html_entity_decode($customerName, ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode(sprintf($data['title'], $this->request->post['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setHtml($this->load->view('extension/module/ticket_mail', $data));
			$mail->send();

			$this->response->redirect($this->url->link('extension/module/tickets/view', '&case_id=' . $this->request->post['case_id'], true));
		}

		$data['action'] = $this->url->link('extension/module/tickets/view', '', true);

		$data['comments'] = array();
		
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

		$results = $this->model_extension_module_tickets->getComments($data['case_id']);

		foreach ($results as $result) {
			$data['comments'][] = array(
				'customer_id'      => $result['customer_id'],
				'user_id'      => $result['user_id'],
				'date_added'      => $result['date_added'],
				'image' => $result['image'] ? $server.'image/'.$result['image']: null,
				'comment_body' => html_entity_decode($result['comment_body'], ENT_QUOTES, 'UTF-8'),
				'delete_href'	=> $this->url->link('extension/module/tickets/deleteComment', '&comment_id=' . $result['comment_id'] .$url, true)
			);
		}
		// var_dump($data['comments']);die;


		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

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

		$url = '';
	
		if (isset($this->request->get['case_id'])) {
			$url .= '&case_id=' . $this->request->get['case_id'];
		}

		$this->response->redirect($this->url->link('extension/module/tickets/view', $url, true));

	}

}