<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2015 Rubén Domínguez nuxsmin@syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace SP\Mgmt;

use SP\Storage\DB;
use SP\Storage\QueryData;
use SP\Util\Util;

defined('APP_ROOT') || die(_('No es posible acceder directamente a este archivo'));

/**
 * Class CustomFieldDef para la gestión de definiciones de campos personalizados
 *
 * @package SP
 */
class CustomFieldDef extends CustomFieldsBase
{
    /**
     * @param string $name   El nombre del campo
     * @param int    $type   El tipo de campo
     * @param int    $module El id del módulo asociado
     */
    public function __construct($name, $type, $module)
    {
        if (!$name || !$type || !$module) {
            throw new \InvalidArgumentException(_('Parámetros incorrectos'));
        }

        $this->_name = $name;
        $this->_type = $type;
        $this->_module = $module;
    }

    /**
     * Eliminar una definición de campo personalizado.
     *
     * @param $id int El id del campo personalizado
     * @return bool
     */
    public static function deleteCustomField($id)
    {
        $query = 'DELETE FROM customFieldsDef WHERE customfielddef_id= :id LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($id, 'id');

        $queryRes = DB::getQuery($Data);

        return $queryRes && CustomFields::deleteCustomFieldForDefinition($id);
    }

    /**
     * Devolver los datos de definiciones de campos personalizados
     *
     * @param int    $limitCount
     * @param int    $limitStart
     * @param string $search La cadena de búsqueda
     * @return array|bool
     */
    public static function getCustomFieldsMgmtSearch($limitCount, $limitStart = 0, $search = '')
    {
        $query = 'SELECT customfielddef_id, customfielddef_module, customfielddef_field '
            . 'FROM customFieldsDef '
            . 'ORDER BY customfielddef_module '
            . 'LIMIT ?, ?';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($limitStart);
        $Data->addParam($limitCount);

        DB::setReturnArray();
        DB::setFullRowCount();

        $queryRes = DB::getResults($Data);

        if ($queryRes === false) {
            return array();
        }

        $customFields = array();

        foreach ($queryRes as $customField) {
            /**
             * @var CustomFieldDef
             */
            $field = unserialize($customField->customfielddef_field);

            if (get_class($field) === '__PHP_Incomplete_Class') {
                $field = Util::castToClass('SP\Mgmt\CustomFieldDef', $field);
            }

            if (empty($search)
                || stripos($field->getName(), $search) !== false
                || stripos(self::getFieldsTypes($field->getType(), true), $search) !== false
                || stripos(self::getFieldsModules($customField->customfielddef_module), $search) !== false
            ) {
                $attribs = new \stdClass();
                $attribs->id = $customField->customfielddef_id;
                $attribs->module = self::getFieldsModules($customField->customfielddef_module);
                $attribs->name = $field->getName();
                $attribs->typeName = self::getFieldsTypes($field->getType(), true);
                $attribs->type = $field->getType();

                $customFields[] = $attribs;
            }
        }

        $customFields['count'] = DB::$lastNumRows;

        return $customFields;
    }

    /**
     * Añadir nuevo campo personalizado
     *
     * @return bool
     */
    public function addCustomField()
    {
        $query = 'INSERT INTO customFieldsDef SET customfielddef_module = :module, customfielddef_field = :field';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->_module, 'module');
        $Data->addParam(serialize($this), 'field');

        $queryRes = DB::getQuery($Data);

        return $queryRes;
    }

    /**
     * Actualizar campo personalizado
     *
     * @return bool
     */
    public function updateCustomField()
    {
        $curField = self::getCustomFields($this->_id, true);

        $query = 'UPDATE customFieldsDef SET ' .
            'customfielddef_module = :module, ' .
            'customfielddef_field = :field ' .
            'WHERE customfielddef_id= :id LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($this->_id, 'id');
        $Data->addParam($this->_module, 'module');
        $Data->addParam(serialize($this), 'field');

        $queryRes = DB::getQuery($Data);

        if ($queryRes && $curField->customfielddef_module !== $this->_module) {
            $queryRes = CustomFields::updateCustomFieldModule($this->_module, $this->_id);
        }

        return $queryRes;
    }

    /**
     * Devolver los datos de definiciones de campos personalizados
     *
     * @param int        $customFieldId El id del campo personalizado
     * @param bool|false $returnRawData Devolver los datos de la consulta sin formatear
     * @return array|bool
     */
    public static function getCustomFields($customFieldId = null, $returnRawData = false)
    {
        $query = 'SELECT customfielddef_id, customfielddef_module, customfielddef_field FROM customFieldsDef';

        $Data = new QueryData();

        if (!is_null($customFieldId)) {
            $query .= ' WHERE customfielddef_id = :id LIMIT 1';
            $Data->addParam($customFieldId, 'id');
        } else {
            $query .= ' ORDER BY customfielddef_module';
        }

        $Data->setQuery($query);

        if (!$returnRawData) {
            DB::setReturnArray();
        }

        $queryRes = DB::getResults($Data);

        if ($queryRes === false) {
            return array();
        }

        if (!$returnRawData) {
            $customFields = array();

            foreach ($queryRes as $customField) {
                /**
                 * @var CustomFieldDef
                 */
                $field = unserialize($customField->customfielddef_field);

                if (get_class($field) === '__PHP_Incomplete_Class') {
                    $field = Util::castToClass('SP\Mgmt\CustomFieldDef', $field);
                }

                $attribs = new \stdClass();
                $attribs->id = $customField->customfielddef_id;
                $attribs->module = self::getFieldsModules($customField->customfielddef_module);
                $attribs->name = $field->getName();
                $attribs->typeName = self::getFieldsTypes($field->getType(), true);
                $attribs->type = $field->getType();

                $customFields[] = $attribs;
            }

            return $customFields;
        }

        return $queryRes;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->_id = $id;
    }
}