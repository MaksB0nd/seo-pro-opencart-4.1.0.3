<?php
namespace Opencart\Catalog\Controller\Startup;

class SeoUrl extends \Opencart\System\Engine\Controller {
	private array $data = [];

	public function index() {
		if ($this->config->get('config_seo_url')) {
			$this->url->addRewrite($this);

			$this->load->model('design/seo_url');

			if (isset($this->request->get['_route_'])) {
				$parts = explode('/', $this->request->get['_route_']);

				if (oc_strlen(end($parts)) == 0) {
					array_pop($parts);
				}

				foreach ($parts as $key => $value) {
					$seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($value);

					if ($seo_url_info) {
						$this->request->get[$seo_url_info['key']] = html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');

						unset($parts[$key]);
					}
				}

				if (!isset($this->request->get['route'])) {
					$this->request->get['route'] = $this->config->get('action_default');
				}

				if ((!empty($this->request->get['path']) || !empty($this->request->get['category_id'])) && empty($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/category';
				}

				if (!empty($this->request->get['manufacturer_id'])) {
					$this->request->get['route'] = 'product/manufacturer.info';
				}

				if (!empty($this->request->get['product_id'])) {
					$this->request->get['route'] = 'product/product';
				}

				if (!empty($this->request->get['information_id'])) {
					$this->request->get['route'] = 'information/information';
				}

				if (!empty($this->request->get['topic_id'])) {
					$this->request->get['route'] = 'cms/blog';
				}

				if (!empty($this->request->get['article_id'])) {
					$this->request->get['route'] = 'cms/blog.info';
				}

				if ($parts) {
					$this->request->get['route'] = $this->config->get('action_error');
				}
			}
		} else {
			if (!isset($this->request->get['route'])) {
				$this->request->get['route'] = $this->config->get('action_default');
			}
		}

		return null;
	}

	public function rewrite(string $link): string {
		$url_info = parse_url(str_replace('&amp;', '&', $link));

		$url = '';

		if ($url_info['scheme']) {
			$url .= $url_info['scheme'];
		}

		$url .= '://';

		if ($url_info['host']) {
			$url .= $url_info['host'];
		}

		if (isset($url_info['port'])) {
			$url .= ':' . $url_info['port'];
		}

		if (!isset($url_info['query'])) {
			return $link;
		}

		parse_str($url_info['query'], $query);

		$paths = [];

		$parts = explode('&', $url_info['query']);

		$route = null;

		foreach ($parts as $part) {
			if (str_starts_with($part, 'route=')) {
				[, $route] = explode('=', $part, 2);
				break;
			}
		}

		switch ($route) {
			case "common/home":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);
				
				break;

			case "product/product":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);
				
				$category_path = null;

				foreach ($parts as $part) {
					if (str_starts_with($part, 'path=')) {
						[, $category_path] = explode('=', $part, 2);
						break;
					}
				}

				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'path=')));
				unset($query['path']);

				break;

			case "product/category":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);

				$category_path = null;

				foreach ($parts as $part) {
					if (str_starts_with($part, 'path=')) {
						[, $category_path] = explode('=', $part, 2);
						break;
					}
				}

				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'path=')));
				unset($query['path']);

			break;

			case "product/manufacturer":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);

				break;

			case "information/information":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);

				break;

			case "cms/blog":
				foreach ($parts as $part) {
					if (str_starts_with($part, 'topic_id=')) {
						[, $topic_id] = explode('=', $part, 2);
						break;
					}
				}

				if (isset($topic_id) && !empty($topic_id)) {
					$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
					unset($query['route']);
				}

				break;

			case "cms/blog.info":
				$parts = array_values(array_filter($parts, fn($p) => !str_starts_with($p, 'route=')));
				unset($query['route']);

				break;
		}

		$language_id = $this->config->get('config_language_id');

		foreach ($parts as $part) {
			$pair = explode('=', $part);

			if (isset($pair[0])) {
				$key = (string)$pair[0];
			}

			if (isset($pair[1])) {
				$value = (string)$pair[1];
			} else {
				$value = '';
			}

			$index = $key . '=' . $value;

			if (!isset($this->data[$language_id][$index])) {
				$this->data[$language_id][$index] = $this->model_design_seo_url->getSeoUrlByKeyValue((string)$key, (string)$value);
			}

			if ($this->data[$language_id][$index]) {
				$paths[] = $this->data[$language_id][$index];

				unset($query[$key]);
			}
		}

		if ($route === 'product/product' || $route === 'product/category') {
			if (!empty($category_path)) {
				$category_url = $this->model_design_seo_url->getSeoUrlByKeyValue('path', $category_path);

				if (str_contains($category_url['keyword'], '/')) {
					$segments = explode('/', $category_url['keyword']);
					$category_url['keyword'] = end($segments);
				}
				$paths[] = $category_url;
			}
		}
		
		$sort_order = [];

		foreach ($paths as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}

		array_multisort($sort_order, SORT_ASC, $paths);

		foreach ($paths as $i => &$path) {
			if ($path['key'] == 'language') {
				if ($path['value'] == $this->config->get('config_language_catalog')) {
					unset($paths[$i]);
				}
			}
		}

		$url .= str_replace('/index.php', '', $url_info['path'] ?? '');

		foreach ($paths as $result) {
			$url .= '/' . $result['keyword'];
		}
		
		if ($query) {
			$url .= '/?' . str_replace(['%2F'], ['/'], http_build_query($query));
			$url = preg_replace('#(?<!:)//+#', '/', $url);
		}

		return $url;
	}
}