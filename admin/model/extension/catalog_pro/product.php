<?php
class ModelExtensionCatalogProProduct extends Model {
    private function filterSql($data = array()) {
        if ($data === array())
            return "";

        $sql = "";
        if (!empty($data['filter_name'])) {
            $sql .= " AND pd.name LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
        }

        if (!empty($data['filter_model'])) {
            $sql .= " AND p.model LIKE '%" . $this->db->escape($data['filter_model']) . "%'";
        }

        if (!empty($data['filter_sku'])) {
            $sql .= " AND p.sku LIKE '%" . $this->db->escape($data['filter_sku']) . "%'";
        }

        if (!empty($data['filter_price'])) {
            $sql .= " AND p.price LIKE '" . $this->db->escape($data['filter_price']) . "%'";
        }

        if (isset($data['filter_quantity']) && $data['filter_quantity'] !== '') {
            $sql .= " AND p.quantity = '" . (int)$data['filter_quantity'] . "'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND p.status = '" . (int)$data['filter_status'] . "'";
        }

        if (!empty($data['filter_product_id'])) {
            if (is_string($data['filter_product_id']))
                $sql .= " AND pd.product_id LIKE '%" . $this->db->escape($data['filter_product_id']) . "%'";
            else if (is_array($data['filter_product_id']))
                $sql .= " AND pd.product_id IN (" . implode(", ", $data['filter_product_id']) . ")";
        }

        if (!empty($data['filter_category'])) {
            $sql .= " AND pc.category_id in (" . $this->db->escape($data['filter_category']) . ")";
        }

        return $sql;
    }

    public function getProduct($product_id) {
        $sql = "SELECT * FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' and p.product_id = '".((int) $product_id)."'";
        $query = $this->db->query($sql);

        $row = $query->row;
        $row['specials'] = $this->getProductSpecials(array($product_id));
        $row['images'] = $this->getProductImages(array($product_id));
        $categories = $this->getProductCategories(array($product_id));
        $row['categories'] = explode(",", ($categories === array()? "": $categories[0]['categories']));

        $row['filters'] = array_map(function($filter) {
            return $filter['filter_id'];
        }, $this->getProductFilters(array($product_id)));

        $row['stores'] = array_map(function($store) {
            return $store['store_id'];
        }, $this->getProductStores(array($product_id)));

        $row['downloads'] = array_map(function($download) {
            return $download['download_id'];
        }, $this->getProductDownloads(array($product_id)));

        $row['related'] = array_map(function($related) {
            return $related['related_id'];
        }, $this->getProductRelated(array($product_id)));

        $row['attributes'] = $this->getProductAttributes(array($product_id));

        return $row;
    }

    public function getProductsByFilter($filter) {
        $sql = "SELECT 
		            p.*, pd.*
		        FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        $sql .= " AND (pd.name like '%".$this->db->escape($filter['q'])."%' or p.model like '%".$this->db->escape($filter['q'])."%' or p.sku like '%".$this->db->escape($filter['q'])."%')";

        if (isset($filter['ignore']) && $filter['ignore'] !== array())
            $sql .= " AND p.product_id not in (".implode(", ", $filter['ignore']).")";

        $sql .= " GROUP BY p.product_id";

        if (isset($data['sort']))
            $sql .= " ORDER BY pd.name ASC";

        if (isset($data['start']) || isset($data['limit'])) {
            if ($filter['start'] < 0) {
                $filter['start'] = 0;
            }

            if ($filter['limit'] < 1) {
                $filter['limit'] = 20;
            }

            $sql .= " LIMIT " . (int)$filter['start'] . "," . (int)$filter['limit'];
        }

        $query = $this->db->query($sql);

        $rows = array();
        foreach ($query->rows as $row)
            $rows[$row['product_id']] = $row;

        $ids = array_keys($rows);

        $specials = $this->getProductSpecials($ids);
        foreach ($specials  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['specials'] = array();

            $rows[$row['product_id']]['specials'][] = $row;
        }

        return $rows;
    }


    public function getProducts($data = array()) {
		$sql = "SELECT 
		            p.*, pd.*, pc.*, p.product_id
		        FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id)
                LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)
                WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        $sql .= $this->filterSql($data);


		$sql .= " GROUP BY p.product_id";

		if (isset($data['sort']))
			$sql .= " ORDER BY " . $data['sort']." ".$data['order'];

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

		$rows = array();
		foreach ($query->rows as $row)
		    $rows[$row['product_id']] = $row;

		$ids = array_keys($rows);

        $specials = $this->getProductSpecials($ids);
        foreach ($specials  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['specials'] = array();

            $rows[$row['product_id']]['specials'][] = $row;
        }

        $images = $this->getProductImages($ids);
        foreach ($images  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['images'] = array();

            $rows[$row['product_id']]['images'][] = $row;
        }

        $categories = $this->getProductCategories($ids);
        foreach ($categories  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['categories'] = array();

            $rows[$row['product_id']]['categories'] = explode(",", $row['categories']);
        }

        $filters = $this->getProductFilters($ids);
        foreach ($filters  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['filters'] = array();

            $rows[$row['product_id']]['filters'][] = $row['filter_id'];
        }

        $stores = $this->getProductStores($ids);
        foreach ($stores  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['filters'] = array();

            $rows[$row['product_id']]['stores'][] = $row['store_id'];
        }

        $downloads = $this->getProductDownloads($ids);
        foreach ($downloads  as $row) {
            if (!isset($rows[$row['product_id']]))
                $rows[$row['product_id']]['downloads'] = array();

            $rows[$row['product_id']]['downloads'][] = $row['download_id'];
        }

		return $rows;
	}


	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_category pc ON (p.product_id = pc.product_id)";

		$sql .= " WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

        $sql .= $this->filterSql($data);

        $query = $this->db->query($sql);

		return $query->row['total'];
	}

    public function getProductSpecials($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_special WHERE product_id in (".implode(",", $ids).") ORDER BY priority, price");

        return $query->rows;
    }

    public function getProductImages($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_image WHERE product_id in (".implode(",", $ids).") ORDER BY sort_order asc");

        return $query->rows;
    }

    public function getProductCategories($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT pc.product_id, group_concat(DISTINCT pc.category_id SEPARATOR ',') as categories FROM " . DB_PREFIX . "product_to_category pc, " . DB_PREFIX . "category c WHERE pc.product_id in (".implode(",", $ids).") and pc.category_id = c.category_id group by pc.product_id ORDER BY c.sort_order asc");

        return $query->rows;
    }

    public function getProductFilters($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_filter where product_id in (".implode(", ", $ids).")");

        return $query->rows;
    }

    public function getProductStores($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_store where product_id in (".implode(", ", $ids).")");

        return $query->rows;
    }

    public function getProductDownloads($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_download where product_id in (".implode(", ", $ids).")");

        return $query->rows;
    }

    public function getProductRelated($ids) {
        if ($ids === array())
            return array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_related where product_id in (".implode(", ", $ids).")");

        return $query->rows;
    }

    public function getProductDescriptions($product_id) {
        $product_description_data = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");

        foreach ($query->rows as $result) {
            $product_description_data[$result['language_id']] = array(
                'name'             => $result['name'],
                'description'      => $result['description'],
                'meta_title'       => $result['meta_title'],
                'meta_description' => $result['meta_description'],
                'meta_keyword'     => $result['meta_keyword'],
                'tag'              => $result['tag']
            );
        }

        return $product_description_data;
    }

    public function getProductAttributes($ids) {
        $attributes = array();

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_attribute WHERE product_id in (".implode(", ", $ids).")");

        foreach ($query->rows as $result) {
            $attributes[$result['attribute_id']][$result['language_id']] = $result['text'];
        }

        return $attributes;
    }

    public function saveProductDescriptions($product_id, $language_id, $values) {
        $sql = array();
        foreach ($values as $field => $value) {
            $sql[] = "`{$field}` = '".$this->db->escape($value)."'";
        }

        $this->db->query("UPDATE " . DB_PREFIX . "product_description set ".implode(",", $sql)." WHERE product_id = '" . (int)$product_id . "' and language_id = '" . (int)$language_id . "'");
        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProduct($product_id, $values) {
        $sql = array();
        foreach ($values as $field => $value) {
            $sql[] = "`{$field}` = '".$this->db->escape($value)."'";
        }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET ".implode(",", $sql).", date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductSpecial($product_id, $specials) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");

        $priority = 0;
        foreach ($specials as $special) {
            $this->db->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$special['customer_group_id'] . "', priority = '" . (int)$priority . "', price = '" . (float)$special['price'] . "', date_start = '" . $this->db->escape($special['date_start']) . "', date_end = '" . $this->db->escape($special['date_end']) . "'");
            $priority++;
        }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductInvertStatus($product_id) {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET `status` = 1 - `status`, date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductImage($product_id, $mainImage, $images) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");

        $sort_order = 0;
        if ($images !== array())
            foreach ($images as $image) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($image['image']) . "', sort_order={$sort_order};");
                $sort_order++;
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET `image` = ".($mainImage == ""? null: "'".$this->db->escape($mainImage)."'").", date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductCategory($product_id, $categories) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");

        if ($categories !== array())
            foreach ($categories as $category) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category . "';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductFilter($product_id, $filters) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");

        if ($filters !== array())
            foreach ($filters as $filter) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter . "';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductStore($product_id, $stores) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int)$product_id . "'");

        if ($stores !== array())
            foreach ($stores as $store) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store . "';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductDownload($product_id, $downloads) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");

        if ($downloads !== array())
            foreach ($downloads as $download) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download . "';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductRelated($product_id, $related) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");

        if ($related !== array())
            foreach ($related as $r) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$r . "';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

    public function saveProductAttributes($product_id, $attributes) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

        if ($attributes !== array())
            foreach ($attributes as $a) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . $a['attribute_id'] . "', language_id = '" . $a['language_id'] . "', text = '".$this->db->escape($a['text'])."';");
            }

        $this->db->query("UPDATE " . DB_PREFIX . "product SET date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
        return;
    }

}
