<?php

namespace Drupal\islandora_hierarchical_access;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\islandora\IslandoraUtils;
use Exception;

class LUTGenerator implements LUTGeneratorInterface {

  protected Connection $database;

  protected EntityTypeManagerInterface $entityTypeManager;

  protected ?array $uniqueFileFields = NULL;

  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritDoc}
   */
  public function regenerate(): void {
    $tx = $this->database->startTransaction();
    try {


      $this->database->truncate(static::TABLE_NAME)->execute();
      $this->generate();
    } catch (Exception $e) {
      $tx->rollBack();
      throw $e;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function generate(EntityInterface $entity = NULL): void {
    $query = $this->database->select('node', 'n');
    $fmo = IslandoraUtils::MEDIA_OF_FIELD;
    $fmo_alias = $query->join('media__' . $fmo, 'fmo', "%alias.{$fmo}_target_id = n.nid");
    $media_alias = $query->join('media', 'm',
      "%alias.mid = {$fmo_alias}.entity_id");

    if ($entity) {
      $query->condition("{$media_alias}.mid", $entity->id());
    }

    $aliases = [];
    foreach ($this->uniqueFileFields() as $field) {
      $field_alias = $query->join("media__{$field}", 'mf',
        "%alias.entity_id = {$media_alias}.mid");
      $aliases[] = "{$field_alias}.{$field}_target_id";
    }
    $file_alias = $query->join('file_managed', 'fm',
      implode(' OR ', array_map(function ($field_alias) {
        return "%alias.fid = $field_alias";
      }, $aliases)));
    $query->fields('n', ['nid'])
      ->fields($media_alias, ['mid'])
      ->fields($file_alias, ['fid']);

    $this->database->insert(static::TABLE_NAME)->from($query)->execute();
  }

  protected function uniqueFileFields(): array {
    if ($this->uniqueFileFields === NULL) {
      $this->uniqueFileFields = [];
      foreach ($this->getFileFields() as $field) {
        $name = $field->get('field_name');
        if (!in_array($name, $this->uniqueFileFields)) {
          $this->uniqueFileFields[] = $name;
        }
      }
    }

    return $this->uniqueFileFields;
  }

  protected function getFileFields(): iterable {
    /** @var \Drupal\media\MediaTypeInterface $types */
    $types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();
    foreach ($types as $type) {
      $field = $type->getSource()->getSourceFieldDefinition($type);
      $item_def = $field->getItemDefinition();
      if ($item_def->getSetting('handler') == 'default:file') {
        yield $field;
      }
    }
  }

}
