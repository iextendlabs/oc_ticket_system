<?php
class ModelExtensionModuleTickets extends Model {
	public function addCase($data) {
		
		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_to_product SET case_title = '" . $this->db->escape($data['case_title']) . "', description = '" . $this->db->escape($data['description']) . "', status = '" . (int)$data['status'] . "', order_id = '" . (int)$data['order_id'] . "', customer_id = '" . (int)$data['customer_id'] . "',date_added = NOW(), date_modified = NOW(), language_id = '" . (int)$this->config->get('config_language_id') . "', image = '" . $this->db->escape($data['image']) . "' ");
		
		$case_id = $this->db->getLastId();

		return $case_id;

	}

	public function editCase($case_id, $data) {
		
		$this->db->query("UPDATE " . DB_PREFIX . "customer_to_product SET case_title = '" . $this->db->escape($data['case_title']) . "', description = '" . $this->db->escape($data['description']) . "', status = '" . (int)$data['status'] . "', order_id = '" . (int)$data['order_id'] . "', date_modified = NOW() WHERE case_id = '" . (int)$case_id . "'");
		
		$this->cache->delete('customer_to_product');
	}

	public function deleteCase($case_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_to_product` WHERE case_id = '" . (int)$case_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_comment` WHERE case_id = '" . (int)$case_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_agent` WHERE case_id = '" . (int)$case_id . "'");

		$this->cache->delete('customer_to_product');
	}

	public function getCase($case_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer_to_product WHERE case_id = '" . (int)$case_id . "' ");

		return $query->row;
	}

	public function getCases($data = array()) {
		$sql = "SELECT cp.case_id, cp.case_title, cp.description,cp.status, cp.date_added, cp.date_modified, cp.order_id, c.firstname,c.lastname,c.customer_id, ta.agent_id,u.username AS agent_name FROM " . DB_PREFIX . "customer_to_product cp LEFT JOIN " . DB_PREFIX . "customer c ON (cp.customer_id = c.customer_id) LEFT JOIN " . DB_PREFIX . "tickets_to_agent ta ON (cp.case_id = ta.case_id) LEFT JOIN " . DB_PREFIX . "user_to_agent ua ON (ta.agent_id = ua.agent_id) LEFT JOIN " . DB_PREFIX . "user u ON(ua.user_id = u.user_id) WHERE cp.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_customer_id'])) {
			$sql .= " AND c.customer_id LIKE '" . $this->db->escape($data['filter_customer_id']) . "%'";
		}

		if (!empty($data['filter_date_start']) && !empty($data['filter_date_end'])) {
			$sql .= " AND cp.date_added BETWEEN '" . $this->db->escape($data['filter_date_start']) . "' AND '" . $this->db->escape($data['filter_date_end']) . "'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND cp.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_user_id'])) {
			$sql .= " AND u.user_id = '" . (int)$data['filter_user_id'] . "'";
		}

		if (!empty($data['filter_agent_id'])) {
			$sql .= " AND ta.agent_id = '" . (int)$data['filter_agent_id'] . "'";
		}

		$sort_data = array(
			'date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " DESC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function closeCase($data) {
		$this->db->query("UPDATE " . DB_PREFIX . "customer_to_product SET status =0 WHERE case_id='" . (int)$data['case_id'] . "'");

		$this->cache->delete('customer_to_product');
	}

	public function getTotalCases($filter_user_id) {

		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "customer_to_product cp LEFT JOIN " . DB_PREFIX . "tickets_to_agent ta ON (cp.case_id = ta.case_id) LEFT JOIN " . DB_PREFIX . "user_to_agent ua ON (ta.agent_id = ua.agent_id) LEFT JOIN " . DB_PREFIX . "user u ON (ua.user_id = u.user_id)";

		if (!empty($filter_user_id)) {
			$sql .= " WHERE u.user_id = '" . (int)$filter_user_id . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}
	
	public function addComment($data){

		$this->db->query("INSERT INTO " . DB_PREFIX . "tickets_to_comment SET case_id = '" . (int)$data['case_id'] . "', comment_body = '" . $this->db->escape($data['comment_body']) . "',user_id = '" . (int)$this->user->getId() . "',date_added = NOW(), image = '" . $this->db->escape($data['image']) . "'");

	}

	public function getComments($case_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tickets_to_comment` WHERE case_id= '" . (int)$case_id . "' ORDER BY date_added ASC");

		return $query->rows;
	}

	public function deleteComment($comment_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_comment` WHERE comment_id = '" . (int)$comment_id . "'");
		
		$this->cache->delete('tickets_to_comment');
	}

	public function getCustomers($data){
		$sql = "SELECT DISTINCT c.customer_id,c.firstname,c.lastname ,c.email FROM " . DB_PREFIX . "customer c WHERE c.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_customer'])) {
			$sql .= " AND c.firstname LIKE '" . $this->db->escape($data['filter_customer']) . "%'";
		}

		if (isset($data['start']) || isset($data['limit'])) {

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getCustomerEmail($customer_id) {
		$query = $this->db->query("SELECT c.email FROM " . DB_PREFIX . "customer c WHERE c.customer_id = '" . (int)$customer_id . "'");

		return $query->row['email'];
	}

	public function getOrdersDetail($customer_id) {
		$query = $this->db->query("SELECT o.order_id,o.date_added, o.total, o.currency_code, o.currency_value FROM " . DB_PREFIX . "order o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) WHERE o.customer_id = '" . (int)$customer_id . "' ORDER BY o.order_id ASC");

		return $query->rows;
	}

	public function getUsers($data) {
		$sql = "SELECT u.user_id,u.username FROM " . DB_PREFIX . "user u WHERE u.user_id NOT IN(SELECT ua.user_id FROM " . DB_PREFIX . "user_to_agent ua)";
		
		if (!empty($data['filter_user'])) {
			$sql .= " AND u.username LIKE '" . $this->db->escape($data['filter_user']) . "%'";
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function addAgent($data) {
		
		$this->db->query("INSERT INTO " . DB_PREFIX . "user_to_agent SET user_id = '" . (int)$data['user_id'] . "' ");
	}

	public function getAgents($data) {
		$sql = "SELECT ua.agent_id ,u.username AS agent_name FROM " . DB_PREFIX . "user u RIGHT JOIN " . DB_PREFIX . "user_to_agent ua ON (u.user_id = ua.user_id)";
		
		if (!empty($data['filter_agent'])) {
			$sql .= " WHERE u.username LIKE '" . $this->db->escape($data['filter_agent']) . "%'";
		}

		if (isset($data['order'])) {
			$sql .= "ORDER BY ua.agent_id ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalAgent() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "user_to_agent`");

		return $query->row['total'];
	}

	public function deleteAgent($agent_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "user_to_agent` WHERE agent_id = '" . (int)$agent_id . "'");
		
		$this->cache->delete('user_to_agent');
	}

	public function ticketAssigning($data) {

		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_agent` WHERE case_id = '" . (int)$data['case_id'] . "'");
		$this->db->query("INSERT INTO " . DB_PREFIX . "tickets_to_agent SET agent_id = '" . (int)$data['agent_id'] . "', case_id = '" . (int)$data['case_id'] . "'");

		return $query->rows;
	}

	public function getOrders() {
		$query = $this->db->query("SELECT DISTINCT(o.order_id),o.date_added, o.total, o.currency_code, o.currency_value FROM " . DB_PREFIX . "customer_to_product cp LEFT JOIN " . DB_PREFIX . "order o ON (cp.order_id = o.order_id) ORDER BY o.order_id ASC");

		return $query->rows;
	}

	public function getTickets($order_id) {
		$query = $this->db->query("SELECT cp.case_id,cp.case_title FROM " . DB_PREFIX . "customer_to_product cp WHERE cp.order_id='" . (int)$order_id . "' ");

		return $query->rows;
	}

	public function getCommentsByCase($data) { 
		$sql = "SELECT cp.case_id,cp.case_title,tc.customer_id,tc.comment_body FROM " . DB_PREFIX . "customer_to_product cp JOIN " . DB_PREFIX . "tickets_to_comment tc ON (cp.case_id = tc.case_id) LEFT JOIN " . DB_PREFIX . "tickets_to_agent ta ON (cp.case_id = ta.case_id) LEFT JOIN " . DB_PREFIX . "user_to_agent ua ON (ta.agent_id = ua.agent_id)  ";

		if (!empty($data['filter_user_id'])) {
			$sql .= " WHERE ua.user_id = '" . (int)$data['filter_user_id'] . "'";
		}
		
		$sql .= "ORDER by tc.comment_id DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}
		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getAgentAndUser() {
		$query = $this->db->query("SELECT u.user_id,u.username, ua.agent_id FROM " . DB_PREFIX . "user_to_agent ua LEFT JOIN " . DB_PREFIX . "user u ON (ua.user_id = u.user_id)");

		return $query->rows;
	}

	public function getAgentsTicktsDetail() {
		$query = $this->db->query("SELECT ua.agent_id,u.username AS agent_name, (SELECT COUNT(*) FROM " . DB_PREFIX . "tickets_to_agent ta LEFT JOIN " . DB_PREFIX . "customer_to_product cp ON (ta.case_id = cp.case_id) WHERE ta.agent_id = ua.agent_id AND cp.status = 1) AS open_ticket , (SELECT COUNT(*) FROM " . DB_PREFIX . "tickets_to_agent ta LEFT JOIN " . DB_PREFIX . "customer_to_product cp ON (ta.case_id = cp.case_id) WHERE ta.agent_id = ua.agent_id AND cp.status = 0) AS close_ticket FROM " . DB_PREFIX . "user_to_agent ua LEFT JOIN " . DB_PREFIX . "user u ON (ua.user_id = u.user_id) ORDER by open_ticket DESC LIMIT 6");

		return $query->rows;
	}

	public function agentToUserGroup($user_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "user u LEFT JOIN " . DB_PREFIX . "user_group ug ON (u.user_group_id = ug.user_group_id) WHERE u.user_id = '" . (int)$user_id . "' AND ug.name = 'Administrator' ");

		return $query->row['total'];
	}

	public function isAgent($user_id) {
		$query = $this->db->query("SELECT COUNT(*) AS isAgent FROM " . DB_PREFIX . "user_to_agent WHERE user_id = '" . (int)$user_id . "'");

		return $query->row['isAgent'];
	}

	public function ticketDateModified($case_id) {

		$this->db->query("UPDATE " . DB_PREFIX . "customer_to_product SET date_modified=NOW() WHERE case_id='" . (int)$case_id . "'");
	
	}

	public function getAgentByTicket($case_id) {
		
		$query = $this->db->query("SELECT COUNT(*) AS total, u.username AS agent_name FROM " . DB_PREFIX . "tickets_to_agent ta LEFT JOIN " . DB_PREFIX . "user_to_agent ua ON(ta.agent_id = ua.agent_id) LEFT JOIN " . DB_PREFIX . "user u ON (ua.user_id = u.user_id) WHERE ta.case_id= '" . (int)$case_id . "'");

		if($query->row['total'] == 0){
			return "(No Agent Assign)";
		}else{
			return $query->row['agent_name'];
		}
	}
}