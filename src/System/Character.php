<?php

namespace BaseFrame\System;

/**
 * класс для поиска символов
 */
class Character {

	// регулярка для поиска эмодзи
	public const EMOJI_REGEX = "/(?:\\p{Emoji_Presentation}|\\p{Extended_Pictographic}|\\x{FE0F}|(?:[\\x{0023}\\x{002A}\\x{0030}-\\x{0039}]\\x{FE0F}?\\x{20E3}))/u";

	/**
	 * регулярка для поиска общезапрещенных спецсимволов из списка
	 *
	 * включает unicode-блоки:
	 * - управляющие символы: x{2000}-x{200F}
	 * - управляющие символы форматирования: x{202C}-x{202D}
	 * - невидимые управляющие символы: x{2060}-x{206F}
	 * - специальные символы: x{FFF0}-x{FFFF}
	 */
	public const COMMON_FORBIDDEN_CHARACTER_REGEX = "/(
    		[\\x{2000}-\\x{200F}]|
      	[\\x{202C}-\\x{202D}]|
      	[\\x{2060}-\\x{206F}]|
      	[\\x{FFF0}-\\x{FFFF}]
	)/xu";

	// регулярка для поиска отдельного списка запрещенные спецсимволов
	public const SPECIAL_CHARACTER_REGEX = "/[!\"#$%&()*+,.\/:;=?@\[\\\\\]_`{|}~<>]/u";

	// регулярка для поиска угловых скобок
	public const ANGLE_BRACKET_REGEX = "/[<>]/u";

	/**
	 * регулярка для поиска fancy текста (соответствующие блоки юникода)
	 *
	 * включает unicode-блоки:
	 * - математические буквы и цифры: x{1D400}-x{1D7FF}
	 * - полуширинные и полноширинные формы: x{FF00}-x{FFEF}
	 * - модификаторы: x{02B0}-x{02FF}
	 * - буквоподобные символы: x{2100}-x{214F}
	 * - обрамлённые буквы и цифры: x{2460}-x{24FF}
	 * - box drawing и блоки: x{2500}-x{259F}
	 * - разные символы: x{2600}-x{26FF} (☀️☑️⚠️✈️)
	 * - декоративные символы: x{2700}-x{27BF}
	 * - нестандартные пробелы и знаки: x{0080}-x{00BF}
	 * - надстрочные и подстрочные символы: x{2070}-x{209F}
	 * - символы: × © ® ¶ ∆ π • Þ ÷ þ
	 */
	public const FANCY_TEXT_REGEX = "/(
		[\\x{1D400}-\\x{1D7FF}]|
		[\\x{FF00}-\\x{FFEF}]|
		[\\x{02B0}-\\x{02FF}]|
		[\\x{2100}-\\x{214F}]|
		[\\x{2460}-\\x{24FF}]|
		[\\x{2500}-\\x{259F}]|
		[\\x{2600}-\\x{26FF}]|
		[\\x{2700}-\\x{27BF}]|
		[\\x{0080}-\\x{00BF}]|
		[\\x{2070}-\\x{209F}]|
		\\x{00D7}|
		\\x{00A9}|
		\\x{00AE}|
		\\x{00B6}|
		\\x{2206}|
		\\x{03C0}|
		\\x{2022}|
		\\x{00DE}|
		\\x{00F7}|
		\\x{00FE}|
	)/xu";

	// регулярка для поиска двойного пробела
	public const DOUBLE_SPACE_REGEX = "/[ ]{2,}/u";

	// регулярка для поиска любого переноса строки
	public const NEWLINE_REGEX = "/\R/u";

	/**
	 * проверяем, на нахождение символов по регулярному выражению
	 */
	public static function isFound(string $string, string $regex):bool {

		// есть ли найденные символы по регулярному выражению
		if (preg_match($regex, $string)) {
			return true;
		}

		return false;
	}

	public static function sanitizeFullForbiddenCharacterRegex(string $string):string {

		return trim(preg_replace([
			Character::EMOJI_REGEX,
			Character::COMMON_FORBIDDEN_CHARACTER_REGEX,
			Character::SPECIAL_CHARACTER_REGEX,
			Character::FANCY_TEXT_REGEX,
			Character::DOUBLE_SPACE_REGEX,
			Character::NEWLINE_REGEX,
		], ["", "", "", "", " ", ""], $string));
	}
}