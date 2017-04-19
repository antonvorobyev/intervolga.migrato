<? namespace Intervolga\Migrato\Data;

use Intervolga\Migrato\Data\Module\Highloadblock\Field;
use Intervolga\Migrato\Data\Module\Highloadblock\HighloadBlock;
use Intervolga\Migrato\Data\Module\Iblock\Element;
use Intervolga\Migrato\Data\Module\Iblock\Iblock;
use Intervolga\Migrato\Data\Module\Iblock\Section;
use Intervolga\Migrato\Data\Module\Iblock\FieldEnum;

abstract class BaseUserField extends BaseData
{
	/**
	 * @return string[]
	 */
	public static function getLangFieldsNames()
	{
		return array(
			"EDIT_FORM_LABEL",
			"LIST_COLUMN_LABEL",
			"LIST_FILTER_LABEL",
			"ERROR_MESSAGE",
			"HELP_MESSAGE",
		);
	}

	public function getList(array $filter = array())
	{
		$result = array();
		$getList = \CUserTypeEntity::getList(array(), $filter);
		while ($userField = $getList->fetch())
		{
			$userField = \CUserTypeEntity::getByID($userField["ID"]);
			if ($this->isCurrentUserField($userField["ENTITY_ID"]))
			{
				if ($record = $this->userFieldToRecord($userField))
				{
					$result[] = $record;
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $userFieldEntityId
	 *
	 * @return bool
	 */
	abstract public function isCurrentUserField($userFieldEntityId);

	/**
	 * @param array $userField
	 *
	 * @return Record
	 */
	protected function userFieldToRecord(array $userField)
	{
		$record = new Record($this);
		$id = RecordId::createNumericId($userField["ID"]);
		$record->setId($id);
		$record->setXmlId($userField["XML_ID"]);
		$fields = array(
			"FIELD_NAME" => $userField["FIELD_NAME"],
			"USER_TYPE_ID" => $userField["USER_TYPE_ID"],
			"SORT" => $userField["SORT"],
			"MULTIPLE" => $userField["MULTIPLE"],
			"MANDATORY" => $userField["MANDATORY"],
			"SHOW_FILTER" => $userField["SHOW_FILTER"],
			"SHOW_IN_LIST" => $userField["SHOW_IN_LIST"],
			"EDIT_IN_LIST" => $userField["EDIT_IN_LIST"],
			"IS_SEARCHABLE" => $userField["IS_SEARCHABLE"],
		);
		if ($userField["SETTINGS"])
		{
			$fields = array_merge($fields, $this->getSettingsFields($userField["SETTINGS"]));
		}
		$fields = array_merge($fields, $this->getLangFields($userField));
		$record->addFieldsRaw($fields);
		foreach ($this->getSettingsLinks($userField["SETTINGS"]) as $name => $link)
		{
			$record->setReference($name, $link);
		}

		return $record;
	}

	/**
	 * @param array $settings
	 *
	 * @return array
	 */
	protected function getSettingsFields(array $settings, $fullname = "")
	{
		$fields = array();
		foreach ($settings as $name => $setting)
		{
			$name = $fullname ? $fullname . "." . $name : $name;
			if (!in_array($name, array_keys($this->getSettingsReferences())))
			{
				if(!is_array($setting))
				{
					$fields["SETTINGS." . $name] = $setting;
				}
				else
				{
					$fields = array_merge($fields, $this->getSettingsFields($setting, $name));
				}
			}
		}

		return $fields;
	}

	/**
	 * @param array $fields список полей
	 * @param array $isDelete удалять ли составные настройки
	 *
	 * @return array список настроек
	 */
	protected function fieldsToArray(&$fields, $cutKey, $isDelete = false)
	{
		$settings = array();
		foreach($fields as $key => $field)
		{
			if(strstr($key, $cutKey) !== false)
			{
				$workSetting = &$settings;
				$keys = explode(".", str_replace($cutKey . ".", "", $key));
				foreach($keys as $pathKey)
				{
					if(end($keys) == $pathKey)
					{
						$workSetting[$pathKey] = $field;
					}
					else
					{
						$workSetting[$pathKey] = $workSetting[$pathKey] ? $workSetting[$pathKey] : array();
						$workSetting = &$workSetting[$pathKey];
					}
				}

				if($isDelete)
				{
					unset($fields[$key]);
				}
			}
		}
		return $settings;
	}

	/**
	 * @param array $userField
	 *
	 * @return array
	 */
	protected function getLangFields(array $userField)
	{
		$fields = array();
		foreach (static::getLangFieldsNames() as $langField)
		{
			foreach ($userField[$langField] as $lang => $message)
			{
				$fields[$langField . "." . $lang] = $message;
			}
		}

		return $fields;
	}

	/**
	 * @param array $settings
	 *
	 * @return Link[]
	 */
	protected function getSettingsLinks(array $settings)
	{
		$links = array();
		foreach ($settings as $name => $setting)
		{
			if ($name == "IBLOCK_ID")
			{
				$iblockIdObject = RecordId::createNumericId($setting);
				$xmlId = Iblock::getInstance()->getXmlId($iblockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLBLOCK_ID")
			{
				$hlBlockIdObject = RecordId::createNumericId($setting);
				$xmlId = HighloadBlock::getInstance()->getXmlId($hlBlockIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
			if ($name == "HLFIELD_ID")
			{
				$userFieldIdObject = RecordId::createNumericId($setting);
				$xmlId = Field::getInstance()->getXmlId($userFieldIdObject);
				$link = clone $this->getReference("SETTINGS.$name");
				$link->setValue($xmlId);
				$links["SETTINGS.$name"] = $link;
			}
		}

		return $links;
	}

	/**
	 * @param \Intervolga\Migrato\Data\Link[] $links
	 *
	 * @return array настройки для привязки к сущности
	 */
	public function getSettingsLinksFields(array $links)
	{
		$settings = array();
		foreach($links as $entity => $link)
		{
			$entity = str_replace("SETTINGS.", "", $entity);
			$xmlId = $link->getValue();
			if($entityId = $this->getSettingsReference($entity)->getTargetData()->findRecord($xmlId))
			{
				$settings[$entity] = $entityId->getValue();
			}

			if ($entity == "IBLOCK_ID")
			{
				$settings["IBLOCK_TYPE_ID"] = \CIBlock::GetArrayByID($settings[$entity], "IBLOCK_TYPE_ID");
			}
		}
		return $settings;
	}

	public function getReferences()
	{
		$references = array();
		foreach ($this->getSettingsReferences() as $name => $link)
		{
			$references["SETTINGS." . $name] = $link;
		}

		return $references;
	}

	/**
	 * @return Link[]
	 */
	public function getSettingsReferences()
	{
		return array(
			"IBLOCK_ID" => new Link(Iblock::getInstance()),
			"HLBLOCK_ID" => new Link(HighloadBlock::getInstance()),
			"HLFIELD_ID" => new Link(Field::getInstance()),
		);
	}

	/**
	 * @param $key
	 *
	 * @return Link
	 */
	public function getSettingsReference($key)
	{
		$references = $this->getSettingsReferences();
		return $references[$key];
	}

	/**
	 * @param \Intervolga\Migrato\Data\Runtime $runtime
	 * @param \Intervolga\Migrato\Data\Record $field
	 * @param mixed $value
	 */
	public function fillRuntime(Runtime $runtime, Record $field, $value)
	{
		$runtimeValue = null;
		$runtimeLink = null;
		if ($field->getFieldRaw("USER_TYPE_ID") == "iblock_element")
		{
			$runtimeLink = $this->getIblockElementLink($value);
		}
		elseif ($field->getFieldRaw("USER_TYPE_ID") == "hlblock")
		{
			$runtimeLink = $this->getHlblockElementLink($field, $value);
		}
		elseif ($field->getFieldRaw("USER_TYPE_ID") == "iblock_section")
		{
			$runtimeLink = $this->getIblockSectionLink($value);
		}
		elseif ($field->getFieldRaw("USER_TYPE_ID") == "enumeration")
		{
			$runtimeLink = $this->getEnumerationLink($value);
		}
		elseif (in_array($field->getFieldRaw("USER_TYPE_ID"), array("string", "double", "boolean", "integer", "datetime", "date", "string_formatted")))
		{
			$runtimeValue = new Value($value);
		}

		if ($runtimeValue)
		{
			$runtime->setField($field->getXmlId(), $runtimeValue);
		}
		if ($runtimeLink)
		{
			if ($field->getFieldRaw("MANDATORY") == "Y")
			{
				$runtime->setDependency($field->getXmlId(), $runtimeLink);
			}
			else
			{
				$runtime->setReference($field->getXmlId(), $runtimeLink);
			}
		}
	}

	public function getXmlIds(Basedata $instance, $value)
	{
		$values = is_array($value) ? $value : array($value);
		$xmlIds = array();
		foreach($values as $value)
		{
			$inObject = RecordId::createNumericId($value);
			$xmlIds[] = $instance->getXmlId($inObject);
		}
		if(count($xmlIds) == 1)
		{
			return new Link($instance, $xmlIds[0]);
		}
		else
		{
			$link = new Link($instance);
			$link->setValues($xmlIds);
			return $link;
		}

	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getIblockElementLink($value)
	{
		return $this->getXmlIds(Element::getInstance(), $value);
	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getIblockSectionLink($value)
	{
		return $this->getXmlIds(Section::getInstance(), $value);
	}

	/** TODO Сделать для множественных значений
	 * @param \Intervolga\Migrato\Data\Record $field
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 * @throws \Exception
	 */
	protected function getHlblockElementLink(Record $field, $value)
	{
		$references = $field->getReferences();
		$hlbElementXmlId = "";
		if ($references["SETTINGS.HLBLOCK_ID"])
		{
			$hlblockXmlId = $references["SETTINGS.HLBLOCK_ID"]->getValue();
			$hlblockIdObject = HighloadBlock::getInstance()->findRecord($hlblockXmlId);
			if ($hlblockIdObject)
			{
				$hlblockId = $hlblockIdObject->getValue();
				$elementIdObject = RecordId::createComplexId(array(
					"ID" => intval($value),
					"HLBLOCK_ID" => intval($hlblockId),
				));
				$hlbElementXmlId = Module\Highloadblock\Element::getInstance()->getXmlId($elementIdObject);
			}
		}

		return new Link(Module\Highloadblock\Element::getInstance(), $hlbElementXmlId);
	}

	/**
	 * @param int $value
	 *
	 * @return \Intervolga\Migrato\Data\Link
	 */
	protected function getEnumerationLink($value)
	{
		return $this->getXmlIds(FieldEnum::getInstance(), $value);
	}

	/**
	 * @return string
	 */
	abstract public function getDependencyString();

	/**
	 * @param $id
	 * @return string
	 */
	abstract public function getDependencyNameKey($id);

	public function update(Record $record)
	{
		$isUpdated = false;
		if ($record->getId()->getValue())
		{
			$fields = $record->getFieldsRaw();

			$blockIdXml = $record->getDependency($this->getDependencyString());

			$fields["SETTINGS"] = $this->fieldsToArray($fields, "SETTINGS", true);
			foreach($this->getLangFieldsNames() as $lang)
			{
				$fields[$lang] = $this->fieldsToArray($fields, $lang, true);
			}

			if(!$blockIdXml)
			{
				$fields["SETTINGS"] = array_merge($fields["SETTINGS"], $this->getSettingsLinksFields($record->getReferences()));
			}

			$fieldObject = new \CUserTypeEntity();
			if ($fields["SETTINGS"])
			{
				$dbUserField = $fieldObject->getList(array(), array("ID" => $record->getId()->getValue()))->fetch();
				if ($dbUserField["SETTINGS"])
				{
					$fields["SETTINGS"] = array_merge($dbUserField["SETTINGS"], $fields["SETTINGS"]);
				}
			}
			$isUpdated = $fieldObject->Update($record->getId()->getValue(), $fields);
		}
		if (!$isUpdated)
		{
			throw new \Exception("Unknown error");
		}
	}

	public function create(Record $record)
	{
		$fields = $record->getFieldsRaw();
		$fields["XML_ID"] = $record->getXmlId();
		$fields["SETTINGS"] = $this->fieldsToArray($fields, "SETTINGS", true);
		foreach($this->getLangFieldsNames() as $lang)
		{
			$fields[$lang] = $this->fieldsToArray($fields, $lang, true);
		}

		$fieldObject = new \CUserTypeEntity();
		$fieldId = $fieldObject->add($fields);
		if ($fieldId)
		{
			return $this->createId($fieldId);
		}
		else
		{
			global $APPLICATION;
			throw new \Exception($APPLICATION->getException()->getString());
		}
	}

	public function delete($xmlId)
	{
		if($id = $this->findRecord($xmlId))
		{
			$fieldObject = new \CUserTypeEntity();
			if (!$fieldObject->delete($id->getValue()))
			{
				throw new \Exception("Unknown error");
			}
		}
	}

	public function setXmlId($id, $xmlId)
	{
		$userFieldObject = new \CUserTypeEntity();
		$userFieldObject->update($id->getValue(), array("XML_ID" => $xmlId));
	}

	public function getXmlId($id)
	{
		$userField = \CUserTypeEntity::getById($id->getValue());

		return $userField["XML_ID"];
	}
}