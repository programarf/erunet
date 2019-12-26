<?php

namespace Drupal\eru_csv_importer\Plugin;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for ImporterBase plugins.
 *
 * @see \Drupal\eru_csv_importer\Annotation\Importer
 * @see \Drupal\eru_csv_importer\Plugin\ImporterManager
 * @see \Drupal\eru_csv_importer\Plugin\ImporterInterface
 * @see plugin_api
 */
abstract class ImporterBase extends PluginBase implements ImporterInterface {

  use StringTranslationTrait;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs ImporterBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function data() {
    $csv = $this->configuration['csv'];
    $return = [];

    if ($csv && is_array($csv)) {
      $csv_fields = $csv[0];
      unset($csv[0]);
      foreach ($csv as $index => $data) {
        foreach ($data as $key => $content) {
          if ($content && isset($csv_fields[$key])) {
            $content = Unicode::convertToUtf8($content, mb_detect_encoding($content));
            $fields = explode('|', $csv_fields[$key]);

            if ($fields[0] == 'translation') {
              if (count($fields) > 3) {
                $return['translations'][$index][$fields[3]][$fields[1]][$fields[2]] = $content;
              }
              else {
                $return['translations'][$index][$fields[2]][$fields[1]] = $content;
              }
            }
            else {
              $field = $fields[0];
              if (count($fields) > 1) {
                foreach ($fields as $key => $in) {
                  $return['content'][$index][$field][$in] = $content;
                }
              }
              elseif (isset($return['content'][$index][$field])) {
                $prev = $return['content'][$index][$field];
                $return['content'][$index][$field] = [];

                if (is_array($prev)) {
                  $prev[] = $content;
                  $return['content'][$index][$field] = $prev;
                }
                else {
                  $return['content'][$index][$field][] = $prev;
                  $return['content'][$index][$field][] = $content;
                }
              }
              else {
                $return['content'][$index][current($fields)] = $content;
              }
            }
          }
        }

        if (isset($return[$index])) {
          $return['content'][$index] = array_intersect_key($return[$index], array_flip($this->configuration['fields']));
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function add($content, array &$context) {
    if (!$content) {
      return NULL;
    }

    $entity_type = $this->configuration['entity_type'];
    $entity_type_bundle = $this->configuration['entity_type_bundle'];
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);

    $added = 0;
    $updated = 0;

    foreach ($content['content'] as $key => $data) {
      if ($entity_definition->hasKey('bundle') && $entity_type_bundle) {
        $data[$entity_definition->getKey('bundle')] = $this->configuration['entity_type_bundle'];
      }

      /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $entity_storage  */
      $entity_storage = $this->entityTypeManager->getStorage($this->configuration['entity_type']);

      try {
        if (isset($data[$entity_definition->getKeys()['id']]) && $entity = $entity_storage->load($data[$entity_definition->getKeys()['id']])) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity  */
          foreach ($data as $id => $set) {
            $entity->set($id, $set);
          }

          $this->preSave($entity, $data, $context);

          if ($entity->save()) {
            $updated++;
          }
        }
        else {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity  */
          // Get field definitions.
          $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $entity_type_bundle);
          foreach ($data as $field => $value) {
            // If field is image type.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'image'
            ) {
              $properties = [
                'title' => $content['content'][$key]['title'] ?? '',
              ];
              $data[$field] = $this->createImage($value, $properties);
            }

            // If field is file type.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'file'
            ) {
              $properties = [
                'title' => $content['content'][$key]['title'] ?? '',
              ];
              $data[$field] = $this->createFile($value, $properties);
            }

            // If field is type date.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'datetime'
            ) {
              if (!empty($definitions[$field]->getSettings()['datetime_type'])
                && $definitions[$field]->getSettings()['datetime_type'] === 'date'
              ) {
                $date_format = \Drupal::configFactory()->getEditable('core.date_format.html_date');
                if (!empty($date_format->getRawData()['pattern'])) {
                  $data[$field] = date($date_format->getRawData()['pattern'], $value);
                }
              }
            }

            // Validate if field is url type.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'link'
            ) {
              $properties = [
                'uri' => $value,
                'title' => $content['content'][$key]['title'] ?? '-',
                'options' => [],
              ];
              $data[$field] = $properties;
              continue;
            }

            // Validate if field is url type.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'text_with_summary'
            ) {
              $summary = '';
              if (!empty($data['summary'])) {
                $summary = $data['summary'];
              }
              $properties = [
                'value' => $value,
                'summary' => $summary,
                'format' => 'basic_html',
              ];
              $data[$field] = $properties;
              continue;
            }

            // Validate if field is field_gallery.
            if ($field === 'field_gallery'
              && !empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'entity_reference_revisions'
            ) {
              $values = explode(',', $value);
              $paragraphs = [];
              foreach ($values as $key_value => $image_value) {
                $image_value = str_replace(' ', '', $image_value);

                $title = $content['content'][$key]['title'] ?? '';
                $properties = [
                  'title' => $title,
                ];
                $value = $this->createImage($image_value, $properties);

                $paragraph = Paragraph::create([
                  'type' => 'galleries',
                  'field_img' => $value,
                  'field_title' => $title,
                ]);
                $paragraph->save();
                $temp_value = [
                  'target_id' => $paragraph->id(),
                  'target_revision_id' => $paragraph->getRevisionId(),
                ];
                array_push($paragraphs, $temp_value);

                // Add field_cover_img if Apply.
                if (empty($data['field_cover_img']) && !empty($definitions['field_cover_img'])) {
                  $value = $this->createImage($image_value, $properties);
                  $data['field_cover_img'] = $value;
                }
              }

              // Create gallery paragraph.
              $paragraph = Paragraph::create([
                'type' => 'gallery',
                'field_galleries_img' => $paragraphs,
              ]);
              $paragraph->save();

              $temp_value = [
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              ];
              $data[$field] = $temp_value;
              continue;
            }

            // If field is tabs.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'entity_reference'
              && method_exists($definitions[$field], 'getSettings')
            ) {
              $storage_definition = $definitions[$field]->getSettings();
              if (!empty($storage_definition['target_type'])
                && $storage_definition['target_type'] === 'taxonomy_term'
                && !empty($storage_definition['handler_settings']['target_bundles']))
              {
                $target_bundles = $storage_definition['handler_settings']['target_bundles'];
                end($target_bundles);
                $bundle = key($target_bundles);
                $values = explode(',', $value);
                $terms = [];
                foreach ($values as $key_term => $value_term) {
                  // Validate if term exist.
                  $value_term = ltrim($value_term);
                  $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(
                    [
                      'name' => $value_term,
                      'vid' => $bundle,
                    ]
                  );
                  $term = reset($term);
                  $no_generate_taxonomy = [
                    'funcionarios',
                    'documento_sig',
                  ];
                  if (empty($term) && !in_array($entity_type_bundle, $no_generate_taxonomy)) {
                    $term = $this->entityTypeManager->getStorage('taxonomy_term')->create(
                      [
                        'name' => $value_term,
                        'vid' => $bundle,
                      ]
                    );
                    $term->save();
                  }
                  // If term is empty, to do nothing.
                  if (!empty($term)) {
                    $tid = ['target_id' => $term->id()];
                    array_push($terms, $tid);
                  }
                }
                $data[$field] = $terms;
                continue;
              }
            }

            // If field is list_string type.
            if (!empty($definitions[$field])
              && method_exists($definitions[$field], 'getType')
              && $definitions[$field]->getType() === 'list_string'
            ) {
              if (!empty($definitions[$field]->getSettings()['allowed_values'])
                && in_array($value, $definitions[$field]->getSettings()['allowed_values'])
              ) {
                $data[$field] = array_search($value, $definitions[$field]->getSettings()['allowed_values']);
              }
            }
          }

          // Delete field summary.
          if (isset($data['summary'])) {
            unset($data['summary']);
          }
          $entity = $this->entityTypeManager->getStorage($this->configuration['entity_type'])->create($data);

          $this->preSave($entity, $data, $context);

          if ($entity->save()) {
            $added++;
          }
        }

        if (isset($content['translations'][$key]) && is_array($content['translations'][$key])) {
          foreach ($content['translations'][$key] as $code => $translations) {
            $entity_data = array_replace($translations, $translations);

            if ($entity->hasTranslation($code)) {
              $entity_translation = $entity->getTranslation($code);

              foreach ($entity_data as $key => $translation_data) {
                $entity_translation->set($key, $translation_data);
              }
            }
            else {
              $entity_translation = $entity->addTranslation($code, $entity_data);
            }

            $entity_translation->save();
          }
        }
      }
      catch (\Exception $e) {
      }
    }

    $context['results'] = [$added, $updated];
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations() {
    $operations[] = [
      [$this, 'add'],
      [$this->data()],
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function finished($success, $results, array $operations) {
    $message = '';

    if ($success) {
      $message = $this->t('@count_added content added and @count_updated updated', ['@count_added' => isset($results[0]) ? $results[0] : 0, '@count_updated' => isset($results[1]) ? $results[1] : 0]);
    }

    drupal_set_message($message);
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    $process = [];
    if ($operations = $this->getOperations()) {
      $process['operations'] = $operations;
    }

    $process['finished'] = [$this, 'finished'];

    batch_set($process);
  }

  /**
   * Override entity before run Entity::save().
   *
   * @param mixed $entity
   *   The entity object.
   * @param array $content
   *   The content array to be saved.
   * @param array $context
   *   The batch context array.
   */
  public function preSave(&$entity, array $content, array &$context) {}

  /**
   * @param $value
   * @param $properties
   * @return array|string
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createImage($value, $properties) {
    $image = '';
    try {
      $value = preg_replace('/\?.*/', '', $value);
      $image_data = file_get_contents($value);
      if (!empty($image_data)) {
        $directory = file_default_scheme() . '://importer_images';
        $prepare_directory = $this->fileSystem->prepareDirectory($directory, FILE_CREATE_DIRECTORY);
        if ($prepare_directory) {
          $real_path = $directory . '/' . end(explode('/', $value));
          $created_file = file_put_contents($real_path, $image_data);
          if (!empty($created_file)) {
            $file = File::Create([
              'uri' => $real_path,
            ]);
            $file->save();
            $image = [
              'target_id' => $file->id(),
              'alt' => $properties['title'] ?? '',
              'title' => $properties['title'] ?? '',
            ];
          }
        }
      }
    }
    catch (\Exception $e) {}
    return $image;
  }

  /**
   * @param $value
   * @param $properties
   * @return array|string
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createFile($value, $properties) {
    $data_file = '';
    try {
      $value = preg_replace('/\?.*/', '', $value);
      $file_data = file_get_contents($value);
      if (!empty($file_data)) {
        $directory = file_default_scheme() . '://importer_files';
        $prepare_directory = $this->fileSystem->prepareDirectory($directory, FILE_CREATE_DIRECTORY);
        if ($prepare_directory) {
          $real_path = $directory . '/' . end(explode('/', $value));
          $created_file = file_put_contents($real_path, $file_data);
          if (!empty($created_file)) {
            $file = File::Create([
              'uri' => $real_path,
            ]);
            $file->save();
            $data_file = [
              'target_id' => $file->id(),
              'display' => 1,
              'description' => '',
            ];
          }
        }
      }
    }
    catch (\Exception $e) {}
    return $data_file;
  }
}
