<?php

namespace Drupal\islandora_hierarchical_access\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Drupal\islandora_hierarchical_access\LUTGeneratorInterface;

/**
 * Hierarchical access drush commands.
 */
class IslandoraHierarchicalAccessCommands extends DrushCommands {

  /**
   * The LUT generator service.
   *
   * @var \Drupal\islandora_hierarchical_access\LUTGeneratorInterface
   */
  protected LUTGeneratorInterface $lutGenerator;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   */
  public function __construct(
    LUTGeneratorInterface $lut_generator,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    parent::__construct();
    $this->lutGenerator = $lut_generator;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Command description here.
   *
   * @param array $options
   *   An associative array of options, as per drush.
   *
   * @option media-ids
   *   A comma-separate list of media IDs, to constrain regeneration.
   * @usage islandora_hierarchical_access:regenerate-lut
   *   Fully rebuilds the lookup table.
   * @usage islandora_hierarchical_access:regenerate-lut --media-ids=2,6
   *
   * @command islandora_hierarchical_access:regenerate-lut
   */
  public function regenerateLut(array $options = ['media-ids' => NULL]) : void {
    if ($options['media-ids']) {
      $media_ids = str_getcsv($options['media-ids']);
      $storage = $this->entityTypeManager->getStorage('media');
      foreach ($media_ids as $media_id) {
        $media = $storage->load($media_id);
        if ($media) {
          $this->lutGenerator->generate($media);
          $this->logger()->info('Regenerated LUT rows for {media_id}.', [
            'media_id' => $media_id,
          ]);
        }
        else {
          $this->logger()->warning("Failed to load {media_id} for LUT regeneration.", [
            'media_id' => $media_id,
          ]);
        }
      }
    }
    else {
      $this->logger()->info('Regenerating full LUT; this could take a while...');
      $this->lutGenerator->regenerate();
      $this->logger()->info('... done!');
    }
  }

}