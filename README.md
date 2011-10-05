#Basic Haystack Usage

##Load the Haystack library
```php
<?php

	$this->load->library('Haystack');
	
?>
```

##Create a new IndexTank search index
```php
<?php

	$this->haystack->create_index('cars');
	
?>
```


##Set the index to work with for the next one or more IndexTank API calls
```php
<?php
	
	$this->haystack->set_index('cars');
?>
```


##Add a single document to the currently selected index
```php
<?php

	$docid = 1;
	$fields = array(
		'text' => 'The Chevrolet Impala is a full-size automobile built by the Chevrolet division of General Motors introduced for the 1958 model year.',
	);
	
	$this->haystack->add_document($docid, $fields);
	
?>
```


##Add multiple documents to the currently selected index
```php
<?php

	$docs = array(
		array(
			'docid' => 1,
			'fields' => array(
				'text' => 'The Chevrolet Impala is a full-size automobile built by the Chevrolet division of General Motors introduced for the 1958 model year.'
			)
		),
		array(
			'docid' => 2,
			'fields' => array(
				'text' => 'The Ford GT is a mid-engine two-seater sports car. Ford Motor Company produced the Ford GT for the 2005 to 2006 model years. The designers drew inspiration from Ford\'s GT40 race cars of the 1960s.'
			)
		)
	);
	
	$this->haystack->add_documents($docs);
	
?>
```


##Delete a single document from the currently selected index
```php
<?php

	$this->haystack->delete_document($docid = 1);
	
?>
```


##Delete multiple documents from the currently selected index
```php
<?php

	$docids = array(1, 2, 3, 10);
	$this->haystack->delete_documents($docids);
	
?>
```


##Select a different search index to work with and add a new document
```php
<?php

	$this->haystack->set_index('trucks');
	$docid = 12;
	$fields = array(
		'text' => 'Bigfoot, introduced in 1979, is regarded as the original monster truck. Other trucks with the name "Bigfoot" have been introduced in the years since, and it remains the most well-known monster truck moniker in the United States.'
	);
	
	$this->haystack->add_document($docid, $fields);
	
?>
```


##Delete an entire search index and all of its documents
```php
<?php

	$this->haystack->delete_index('vans');
	
?>
```


##Search in the currently selected index
```php
<?php

	$terms = "monster truck";
	$results = $this->haystack->search($terms);

	if($results->matches == 0)
	{
		// No results found
	}
	else
	{
		// Results found. Loop through them and print the id.
		foreach($results->results as $doc)
		{
			echo "Found document {$doc->docid}<br />";
		}
	}
	
?>
```