<?php
/**
 * Copyright © Q-Solutions Studio: eCommerce Nanobots. All rights reserved.
 *
 * @category    Nanobots
 * @package     Nanobots_MigrationTool
 * @author      Jakub Winkler <jwinkler@qsolutionsstudio.com
 * @author      Sebastian Strojwas <sebastian@qsolutionsstudio.com>
 * @author      Wojtek Wnuk <wojtek@qsolutionsstudio.com>
 * @author      Lukasz Owczarczuk <lukasz@qsolutionsstudio.com>
 */

namespace Nanobots\MigrationTool\Model\Import\Cms;

use Magento\Framework\Exception\LocalizedException;
use Nanobots\MigrationTool\Model\Import\SyncAbstract;
use Zend_Db_Adapter_Exception;
use Zend_Db_Exception;
use Zend_Db_Statement_Exception;

class Pages extends SyncAbstract
{
    /**
     * @var string
     */
    protected string $entityName = 'cms_page';

    /**
     * @var bool
     */
    protected bool $truncate = true;

    /**
     * @var bool
     */
    protected bool $matchMissingColumns = false;

    /**
     * @param $m1Entity
     * @param $matchingColumns
     * @return array
     */
    public function prepareRowToInsert($m1Entity, $matchingColumns): array
    {
        $css = '';
        if (isset($m1Entity['root_template'])) {
            switch ($m1Entity['root_template']) {
                case 'one_column_cms': {
                    $m1Entity['page_layout'] = '1-column-cms-page';
                    break;
                }
                default:
                {
                    $m1Entity['page_layout'] = '1column';
                    break;
                }
            }

            if ($m1Entity['page_id'] == 2) {
                $m1Entity['is_active'] = 1;
            }

            $css = $this->extractCssFromXML($m1Entity['custom_layout_update_xml']);
        }

        unset($m1Entity['custom_layout_update_xml']);
        unset($m1Entity['layout_update_xml']);
        unset($m1Entity['root_template']);
        unset($m1Entity['position']);

        if ($css) {
            $m1Entity['content'] = trim($css) . PHP_EOL . $m1Entity['content'];
        }

        return $m1Entity;
    }

    /**
     * @param string|null $xml
     * @return string
     */
    private function extractCssFromXML(?string $xml): string
    {
        if ($xml) {
            $m1Xml = "<?xml version='1.0'?>" . PHP_EOL . $xml;
            $m1Xml = simplexml_load_string($m1Xml);
            return (string)$m1Xml->xpath('/reference/block/action/text')[0];
        }
        return '';
    }

    /**
     * @param bool $delta
     * @throws LocalizedException|Zend_Db_Adapter_Exception|Zend_Db_Exception|Zend_Db_Statement_Exception
     */
    public function sync(bool $delta = false): void
    {
        parent::sync();
        $this->truncate = true;
        $this->syncData('cms_page_store', 'cms_page_store');
        $this->updateStatus();

        // fix URL Keys for category stores
        $this->connectionHelper->getM2Connection()->query('delete from url_rewrite where target_path like "%cms/page/view%"');

        $cmsPages = $this->connectionHelper->getM2Connection()->fetchAssoc(
            'select * from cms_page where page_id > 1'
        );

        foreach ($cmsPages as $cmsPage) {
            $cmsPageStores = $this->connectionHelper->getM2Connection()->fetchAssoc(
                'select * from cms_page_store where page_id = ?' , $cmsPage['page_id']
            );

            foreach ($cmsPageStores as $cmsPageStore) {
                $this->connectionHelper->getM2Connection()->insert(
                    'url_rewrite',
                    [
                        'entity_type' => 'cms-page',
                        'entity_id' => $cmsPage['page_id'],
                        'request_path' => $cmsPage['identifier'],
                        'target_path' => sprintf('cms/page/view/id/%s', $cmsPage['page_id']),
                        'redirect_type' => 0,
                        'store_id' => $cmsPageStore['store_id'],
                        'description' => NULL,
                        'is_autogenerated' => 1,
                        'metadata' => NULL,
                        'pretty_mod_key' => NULL,
                        'id_path' => NULL,
                        'options' => NULL,
                    ]
                );
            }
        }

        echo "end..." .  PHP_EOL;
    }

    /**
     * @return string[]
     */
    protected function getTablesToSync(): array
    {
        return [
            'cms_page' => 'cms_page',
        ];
    }

    /**
     * @return int|null
     */
    protected function getEntityTypeId(): ?int
    {
        return null;
    }

    /**
     * @return string
     */
    protected function getUpdatedAtField(): string
    {
        return 'update_time';
    }
}