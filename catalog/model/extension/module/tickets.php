<?php
class ModelExtensionModuleTickets extends Model {
	public function addCase($data) {

		$this->db->query("INSERT INTO " . DB_PREFIX . "customer_to_product SET case_title = '" . $this->db->escape($data['case_title']) . "', description = '" . $this->db->escape($data['description']) . "', status = 1, customer_id = '" . (int)$this->customer->getId() . "', order_id = '" . (int)$data['order_id'] . "',date_added = NOW(), date_modified = NOW() , language_id = '" . (int)$this->config->get('config_language_id') . "', image = '" . $this->db->escape($data['image']) . "' ");
		
		$case_id = $this->db->getLastId();
		
		return $case_id;
	}

	public function editCase($case_id, $data) {
		
		$this->db->query("UPDATE " . DB_PREFIX . "customer_to_product SET case_title = '" . $this->db->escape($data['case_title']) . "', description = '" . $this->db->escape($data['description']) . "', status = '" . (int)$data['status'] . "', order_id = '" . (int)$data['order_id'] . "', customer_id = '" . (int)$this->customer->getId() . "', date_modified = NOW() WHERE case_id = '" . (int)$case_id . "'");
		
		$this->cache->delete('customer_to_product');
	}

	public function deleteCase($case_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_to_product` WHERE case_id = '" . (int)$case_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_comment` WHERE case_id = '" . (int)$case_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_agent` WHERE case_id = '" . (int)$case_id . "'");

		$this->cache->delete('customer_to_product');
	}

	public function getCase($case_id) {
		$query = $this->db->query("SELECT DISTINCT * FROM " . DB_PREFIX . "customer_to_product WHERE case_id = '" . (int)$case_id . "'");

		return $query->row;
	}

	public function getCases($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "customer_to_product` WHERE customer_id = '" . (int)$this->customer->getId() . "' ";

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
			$sql .= " ASC";
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

	public function getTotalCases() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_to_product` WHERE customer_id = '" . (int)$this->customer->getId() . "'");

		return $query->row['total'];
	}

	public function getOrders() {

		$query = $this->db->query("SELECT o.order_id,o.date_added, o.total, o.currency_code, o.currency_value FROM " . DB_PREFIX . "order o LEFT JOIN " . DB_PREFIX . "order_status os ON (o.order_status_id = os.order_status_id) WHERE o.customer_id = '" . (int)$this->customer->getId() . "' ORDER BY o.order_id ASC");

		return $query->rows;
	}
	
	public function addComment($data){

		$this->db->query("INSERT INTO " . DB_PREFIX . "tickets_to_comment SET case_id = '" . (int)$data['case_id'] . "', comment_body = '" . $this->db->escape($data['comment_body']) . "',customer_id = '" . (int)$this->customer->getId() . "',date_added = NOW(), image = '" . $this->db->escape($data['image']) . "'");

	}

	public function getComments($case_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tickets_to_comment` WHERE case_id= '" . (int)$case_id . "' ORDER BY date_added ASC");

		return $query->rows;
	}

	public function deleteComment($comment_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "tickets_to_comment` WHERE comment_id = '" . (int)$comment_id . "'");
		
		$this->cache->delete('tickets_to_comment');
	}

	public function getCustomerdetail() {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "customer WHERE customer_id = 1");
		
		return $query->rows;
	}

	public function ticketDateModified($case_id) {

		$this->db->query("UPDATE " . DB_PREFIX . "customer_to_product SET date_modified=NOW() WHERE case_id='" . (int)$case_id . "'");
	
	}
}