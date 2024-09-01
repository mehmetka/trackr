<?php

namespace App\util;

use Typesense\Exceptions\TypesenseClientError;
use Typesense\Client;

class Typesense
{
    private $typesenseClient;
    private $collectionName;

    public function __construct($collectionName)
    {
        $this->typesenseClient = new Client([
            'api_key' => $_ENV['TYPESENSE_API_KEY'],
            'nodes' => [
                [
                    'host' => $_ENV['TYPESENSE_HOST'],
                    'port' => $_ENV['TYPESENSE_PORT'],
                    'protocol' => 'http'
                ]
            ],
            'connection_timeout_seconds' => 2
        ]);

        $this->collectionName = $collectionName;
    }

    public function createCollection($schema): array
    {
        try {
            return $this->typesenseClient->collections->create($schema);
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function deleteCollection($collectionName)
    {
        try {
            return $this->typesenseClient->collections[$this->collectionName]->delete();
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function indexDocument($document): array
    {
        try {
            return $this->typesenseClient->collections[$this->collectionName]->documents->create($document);
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function searchDocuments($searchParameters): array
    {
        try {
            return $this->typesenseClient->collections[$this->collectionName]->documents->search($searchParameters);
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function updateDocument($document): array
    {
        // Can be used to update or delete a document.
        // To delete a document, set the 'is_deleted' key to true.
        try {
            return $this->typesenseClient->collections[$this->collectionName]->documents->upsert($document);
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function retrieveDocument($documentId): array
    {
        try {
            return $this->typesenseClient->collections[$this->collectionName]->documents[$documentId]->retrieve();
        } catch (TypesenseClientError $e) {
            return ['error' => $e->getMessage()];
        }

    }

}