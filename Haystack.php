<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Haystack
{
	private $ci;
	private $private_url;
	private $public_url;
	private $search_index;					// The index we are interacting with
	private $failed_documents;
	private $api_call_url;
	private $stopwords;
	
	const INDEX_TANK_INDEX_VERSION 	= 'v1/indexes';
	const INDEX_TANK_DOCUMENT 		= 'docs';
	const INDEX_TANK_VARIABLES 		= 'docs/variables';
	const INDEX_TANK_CATEGORIES 	= 'docs/categories';

	function __construct()
	{
		$this->ci =& get_instance();
		
		// Set config options
		$this->ci->config->load('haystack');
		
		$this->private_url = $this->ci->config->item('private_url');
		$this->public_url = $this->ci->config->item('public_url');
		$this->stopwords = $this->ci->config->item('stopwords');
	}
	
	/**
	 * Set the index the next one or more API calls will target.
	 * 
	 * @access public
	 * @param mixed $index_name
	 * @return void
	 */
	function set_index($index)
	{
		$this->search_index = $index;
	}
	
	/**
	 * Get the index we are currently making API calls to.
	 * 
	 * @access private
	 * @return string
	 */
	private function get_index()
	{
		return $this->search_index;
	}
	
	/**
	 * Retrieve the IndexTank private URL.
	 * 
	 * @access private
	 * @return void
	 */
	private function get_private_url()
	{
		return $this->private_url;
	}
	
	/**
	 * Compile the full URL for the next API call.
	 * 
	 * @access private
	 * @param mixed $attribute
	 * @return void
	 */
	private function set_api_call_url($attribute = NULL)
	{
		if( ! is_null($attribute))
		{
			$this->api_call_url = $this->get_private_url().'/'.Haystack::INDEX_TANK_INDEX_VERSION.'/'.$this->get_index().'/'.$attribute;
		}
		else
		{
			$this->api_call_url = $this->get_private_url().'/'.Haystack::INDEX_TANK_INDEX_VERSION.'/'.$this->get_index();
		}
	}
	
	/**
	 * Retrieve the URL for the next API call.
	 * 
	 * @access private
	 * @return void
	 */
	private function get_api_call_url()
	{
		return $this->api_call_url;
	}
	
	/**
	 * Return an array of failed documents from the last API call.
	 * 
	 * @access public
	 * @return Array of failed documents or empty array if no failures were logged on the last API call
	 */
	function get_failed_documents()
	{
		return $this->failed_documents;
	}
	
	/**
	 * Make an API call to a search index.
	 * 
	 * @access private
	 * @param string $method
	 * @param array $params (default: array())
	 * @param array $options (default: array())
	 * @return void
	 */
	private function api_call($method, $params = array(), $options = array())
	{
		// Reset the failed documents on each API call
		$this->failed_documents = array();
		
		$args = '';
		$url = $this->get_api_call_url();
		
		
		if($method == 'GET')
		{
			$url .= '?' . http_build_query($params);
		}
		else
		{
			// Add support for batch indexing and batch deleting. We need to pass IndexTank a JSON list, rather an associative array
			if(in_array('BATCH_INDEX', $options) OR in_array('BATCH_DELETE', $options))
			{
				foreach($params as $doc)
				{
					$docs[] = json_encode($doc, JSON_FORCE_OBJECT);
				}
				
				$args = '[' . implode(',', $docs) . ']';
			}
			else
			{
				$args = json_encode($params, JSON_FORCE_OBJECT);
			}
		} 
		
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method); // Tell curl to use HTTP method of choice
        curl_setopt($curl, CURLOPT_POSTFIELDS, $args); // Tell curl that this is the body of the POST
        curl_setopt($curl, CURLOPT_HEADER, false); // Tell curl not to return headers
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Tell curl to return the response
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Expect:')); //Fixes the HTTP/1.1 417 Expectation Failed
        
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        // Return the status and response body of the API call to the caller. Let the calling function determine if an error occurred.
        return (object) array('status' => $code, 'body' => json_decode($body));
	}

	/**
	 * Retrieve the meta data for the specified index. If index_name is '', retrieve the meta data for all indexes.
	 * 
	 * @access public
	 * @param string $index_name (default: '')
	 * @return stdClass object on success, boolean FALSE on failure
	 */
	function get_meta_data($index = '')
	{
		$this->set_index($index);
		$this->set_api_call_url();
		
		$response = $this->api_call('GET');
		
		switch($response->status)
		{
			case 200:
				return $response->body;
			case 404:
				log_message('error', 'IndexTank 404: No Index existed for the given name.');
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}

	/**
	 * Create a new IndexTank Search Index with the given name.
	 * 
	 * @access public
	 * @param string $index_name
	 * @param bool $public_search (default: FALSE)
	 * @return IndexTank meta data on success, FALSE on failure
	 */
	function create_index($index_name, $public_search = FALSE)
	{
		$index = str_replace('/', '_', $index_name);	// Index names cannot contain forward slashes
		
		$this->set_index($index);
		$this->set_api_cal_url();
		
		$response = $this->api_call('PUT', array('public_search' => $public_search));
		
		switch($response->status)
		{
			case 201:
				return $response->body;
			case 204:
				log_message('error', "IndexTank 204: An index already existed for that name ($index).");
				return FALSE;
			case 409:
				log_message('error', "Too many indexes for this account.");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}
	
	/**
	 * Delete the specified index.
	 * 
	 * @access public
	 * @param string $index_name
	 * @return bool
	 */
	function delete_index($index)
	{
		$this->set_index($index);
		$this->set_api_call_url();
		
		$response = $this->api_call('DELETE');
		
		switch($response->status)
		{
			case 200:
				return TRUE;
			case 204:
				log_message('error', "IndexTank 204: No indexes existed for that name ($index).");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}

	/**
	 * Add a document to the currently selected search index.
	 * 
	 * @access public
	 * @param int $docid
	 * @param array $fields
	 * @param array $variables (default: NULL)
	 * @param array $categories (default: NULL)
	 * @return bool
	 */
	function add_document($docid, $fields, $variables = NULL, $categories = NULL)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_DOCUMENT);
		
		$doc = array(
			'docid' => $docid,
			'fields' => $fields
		);
		
		// Add document variables
		if( ! is_null($variables))
		{
			$doc['variables'] = $variables;
		}
		
		// Add document categories
		if( ! is_null($categories))
		{
			$doc['categories'] = $this->to_string($categories);
		}
		
		$response = $this->api_call('PUT', $doc);
		
		// Failure codes
		switch($response->status)
		{
			case 200:
				return TRUE;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}

	/**
	 * Add multiple documents to the currently selected search index.
	 * 
	 * @access public
	 * @param array $documents
	 * @return bool
	 */
	function add_documents($documents)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_DOCUMENT);
		
		foreach($documents as $i => $document)
		{
			// Force all document category values to be strings
			if(isset($document['categories']))
			{
				$documents[$i]['categories'] = $this->to_string($document['categories']);
			}
		}

		$response = $this->api_call('PUT', $documents, $options = array('BATCH_INDEX'));
		
		// Failure codes
		switch($response->status)
		{
			case 200:
				break;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
		
		// Verify that each document was added
		foreach($response->body as $key => $doc)
		{
			if( ! $doc->added)
			{
				$this->failed_documents[] = $documents[$key];
			}
		}
		
		return empty($this->failed_documents) ? TRUE : FALSE;
	}

	/**
	 * Delete a document from the currently selected search index.
	 * 
	 * @access public
	 * @param int $docid
	 * @return void
	 */
	function delete_document($docid)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_DOCUMENT);
		
		$doc = array(
			'docid' => $docid
		);
		
		$response = $this->api_call('DELETE', $doc);

		switch($response->status)
		{
			case 200:
				return TRUE;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}

	/**
	 * Delete multiple documents from the selected search index.
	 * 
	 * @access public
	 * @param array $docids
	 * @return bool
	 */
	function delete_documents($docids)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_DOCUMENT);
		
		foreach($docids as $docid)
		{
			$docs[] = array('docid' => $docid);
		}
		
		$response = $this->api_call('DELETE', $docs, $options = array('BATCH_DELETE'));
		
		switch($response->status)
		{
			case 200:
				break;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
		
		// Verify that each document was deleted
		foreach($response->body as $key => $doc)
		{
			if( ! $doc->deleted)
			{
				$this->failed_documents[] = $docids[$key];
			}
		}
		
		return empty($this->failed_documents) ? TRUE : FALSE;
	}

	/**
	 * Update a document's variables.
	 * 
	 * @access public
	 * @param int $docid
	 * @param array $variables
	 * @return bool
	 */
	function update_variables($docid, $variables)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_VARIABLES);
		
		$doc = array(
			'docid' => $docid,
			'variables' => $variables
		);
		
		$response = $this->api_call('PUT', $doc);
		
		switch($response->status)
		{
			case 200:
				return TRUE;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}

	/**
	 * Update a document's categories.
	 * 
	 * @access public
	 * @param int $docid
	 * @param array $categories
	 * @return bool
	 */
	function update_categories($docid, $categories)
	{
		$this->set_api_call_url(Haystack::INDEX_TANK_CATEGORIES);
		
		$doc = array(
			'docid' => $docid,
			'categories' => $this->to_string($categories)
		);
		
		$response = $this->api_call('PUT', $doc);

		switch($response->status)
		{
			case 200:
				return TRUE;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}
	
	
	/**
	 * Search the currently selected index.
	 * 
	 * @access public
	 * @param mixed $terms
	 * @param mixed $start (default: NULL)
	 * @param mixed $len (default: NULL)
	 * @param mixed $scoring_function (default: NULL)
	 * @param mixed $fetch_fields (default: NULL)
	 * @param mixed $fetch_variables (default: TRUE)
	 * @param mixed $fetch_categories (default: TRUE)
	 * @param mixed $snippet (default: NULL)
	 * @param mixed $variables (default: NULL)
	 * @param mixed $category_filters (default: NULL)
	 * @param mixed $filter_docvars (default: NULL)
	 * @param mixed $filter_functions (default: NULL)
	 * @return array
	 */
	function search($terms, $start = NULL, $len = NULL, $scoring_function = NULL, $fetch_fields = NULL, $fetch_variables = TRUE, $fetch_categories = TRUE, $snippet = NULL, $variables = NULL, $category_filters = NULL, $filter_docvars = NULL, $filter_functions = NULL)
	{
		// Filter stopwords
		$terms = $this->filter_stopwords($terms);
		
		$query['q'] = $terms;
		
		if( ! is_null($start))
		{
			$query['start'] = $start;
		}
		
		if( ! is_null($len))
		{
			$query['len'] = $len;
		}
		
		if( ! is_null($scoring_function))
		{
			$query['function'] = (string) $scoring_function;
		}
		
		if( ! is_null($fetch_fields))
		{
			$query['fetch'] = $fetch_fields;
		}
		
		$query['fetch_variables'] = $fetch_variables;
		$query['fetch_categories'] = $fetch_categories;
		
		if( ! is_null($snippet))
		{
			$query['snippet'] = $snippet;
		}
		
		if( ! is_null($variables))
		{
			foreach($variables as $key => $value)
			{
				$query["var{$key}"] = $value;
			}
		}
		
		if( ! is_null($category_filters))
		{
			$query['category_filters'] = json_encode($category_filters);
		}
		
		// Expects array(0 => 1:5,12:*') to return docs where var0 is between 1 and 5 or higher than 12
		if( ! is_null($filter_docvars))
		{
			foreach($filter_docvars as $key => $value)
			{
				$query["filter_docvar{$key}"] = $value;
			}
		}
		
		// Expects array(0 => 1:5,12:*') to return docs where funtion0 is between 1 and 5 or higher than 12
		if( ! is_null($filter_functions))
		{
			foreach($filter_functions as $key => $value)
			{
				$query["filter_function{$key}"] = $value;
			}
		}
		
		$this->set_api_call_url('search');
		
		$response = $this->api_call('GET', $query);

		
		switch($response->status)
		{
			case 200:
				return $response->body;
			case 400:
				log_message('error', "IndexTank 400: Invalid or missing argument. {$response->body}");
				return FALSE;
			case 404:
				log_message('error', "IndexTank 404: No index existed for the given name. {$response->body}");
				return FALSE;
			case 409:
				log_message('error', "IndexTank 409: The index was initializing. {$response->body}");
				return FALSE;
			case 503:
				log_message('error', "IndexTank 503: Service unavailable. {$response->body}");
				return FALSE;
			default:
				log_message('error', "IndexTank {$response->status}: An undefined error occurred.");
				return FALSE;
		}
	}
	
	
	
	
	
	
	
	
	
	/**
	 * Helper function to cast all array values to strings. IndexTank will not accept numeric values for document categories.
	 * 
	 * @access private
	 * @param mixed $array
	 * @return array
	 */
	private function to_string($array)
	{
		foreach($array as $key => $value)
		{
			$array[$key] = (string) $value;
		}
		
		return $array;
	}
	
	
	/**
	 * Remove the set of stopwords defined in the config file from the search terms.
	 * 
	 * @access private
	 * @param string $terms
	 * @return string
	 */
	private function filter_stopwords($terms)
	{
		return preg_replace('/\b('.implode('|',$this->stopwords).')\b/','',$terms);
	}
}