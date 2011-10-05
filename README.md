#Basic Haystack Usage

##Load the Haystack library
<code>
$this->load->library('Haystack');
</code>

##Create a new IndexTank search index
<code>
$this->haystack->create_index('cars');
</code>

##Set the index to work with for the next one or more IndexTank API calls
<code>
$this->haystack->set_index('cars');
</code>

##Add a single document to the currently selected index
<code>
$docid = 1;
$fields = array(
	'text' => 'The Chevrolet Impala is a full-size automobile built by the Chevrolet division of General Motors introduced for the 1958 model year.',
);

$this->haystack->add_document($docid, $fields);
</code>

##Add multiple documents to the currently selected index
<code>
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
</code>

##Delete a single document from the currently selected index
<code>
$this->haystack->delete_document($docid = 1);
</code>

##Delete multiple documents from the currently selected index
<code>
$docids = array(1, 2, 3, 10);
$this->haystack->delete_documents($docids);
</code>

##Select a different search index to work with and add a new document
<code>
$this->haystack->set_index('trucks');
$docid = 12;
$fields = array(
	'text' => 'Bigfoot, introduced in 1979, is regarded as the original monster truck. Other trucks with the name "Bigfoot" have been introduced in the years since, and it remains the most well-known monster truck moniker in the United States.'
);

$this->haystack->add_document($docid, $fields);
</code>

##Delete an entire search index and all of its documents
<code>
$this->haystack->delete_index('vans');
</code>

##Search in the currently selected index
<code>
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
</code>