<?php
/**
 * App class.
 *
 * @package FormyChat
 * @since 1.0.0
 */
// Namespace .
namespace FormyChat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\App' ) ) {
	/**
	 * App class.
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	class App {

		/**
		 * Returns the default fonts.
		 *
		 * @return array
		 */
		public static function fonts() {
			$fonts = [
				'sans-serif'       => __( 'Default', 'social-contact-form' ),
				'Arial'            => __( 'Arial', 'social-contact-form' ),
				'Arial Black'      => __( 'Arial Black', 'social-contact-form' ),
				'Comic Sans'       => __( 'Comic Sans MS', 'social-contact-form' ),
				'Courier New'      => __( 'Courier New', 'social-contact-form' ),
				'Georgia'          => __( 'Georgia', 'social-contact-form' ),
				'Lucida Console'   => __( 'Lucida Console', 'social-contact-form' ),
				'Lucida Sans'      => __( 'Lucida Sans', 'social-contact-form' ),
				'Tahoma'           => __( 'Tahoma', 'social-contact-form' ),
				'Times New Roman'  => __( 'Times New Roman', 'social-contact-form' ),
				'Trebuchet'        => __( 'Trebuchet', 'social-contact-form' ),
				'Verdana'          => __( 'Verdana', 'social-contact-form' ),
				'Ubuntu'           => __( 'Ubuntu', 'social-contact-form' ),

				'Roboto'           => __( 'Roboto', 'social-contact-form' ),
				'Roboto Condensed' => __( 'Roboto Condensed', 'social-contact-form' ),
				'Open Sans'        => __( 'Open Sans', 'social-contact-form' ),
				'Lato'             => __( 'Lato', 'social-contact-form' ),
				'Montserrat'       => __( 'Montserrat', 'social-contact-form' ),
				'Raleway'          => __( 'Raleway', 'social-contact-form' ),
				'PT Sans'          => __( 'PT Sans', 'social-contact-form' ),
				'Roboto Slab'      => __( 'Roboto Slab', 'social-contact-form' ),
				'Merriweather'     => __( 'Merriweather', 'social-contact-form' ),
				'Playfair Display' => __( 'Playfair Display', 'social-contact-form' ),
				'Source Sans Pro'  => __( 'Source Sans Pro', 'social-contact-form' ),
				'Noto Sans'        => __( 'Noto Sans', 'social-contact-form' ),
				'Noto Serif'       => __( 'Noto Serif', 'social-contact-form' ),
				'Roboto Mono'      => __( 'Roboto Mono', 'social-contact-form' ),
				'Nunito'           => __( 'Nunito', 'social-contact-form' ),
				'Poppins'          => __( 'Poppins', 'social-contact-form' ),
				'Rubik'            => __( 'Rubik', 'social-contact-form' ),

			];

			return apply_filters( 'scf_fonts', $fonts );
		}

		/**
		 * Returns the default countries.
		 *
		 * @return array
		 */
		public static function countries() {
			$countries = [
				[
					'name' => 'Afghanistan',
					'code' => '93',
					'flag' => '🇦🇫',
				],
				[
					'name' => 'Aland Islands',
					'code' => '35818',
					'flag' => '🇦🇽',
				],
				[
					'name' => 'Albania',
					'code' => '355',
					'flag' => '🇦🇱',
				],
				[
					'name' => 'Algeria',
					'code' => '213',
					'flag' => '🇩🇿',
				],
				[
					'name' => 'American Samoa',
					'code' => '1684',
					'flag' => '🇦🇸',
				],
				[
					'name' => 'Andorra',
					'code' => '376',
					'flag' => '🇦🇩',
				],
				[
					'name' => 'Angola',
					'code' => '244',
					'flag' => '🇦🇴',
				],
				[
					'name' => 'Anguilla',
					'code' => '1264',
					'flag' => '🇦🇮',
				],
				[
					'name' => 'Antarctica',
					'code' => '672',
					'flag' => '🇦🇶',
				],
				[
					'name' => 'Antigua And Barbuda',
					'code' => '1268',
					'flag' => '🇦🇬',
				],
				[
					'name' => 'Argentina',
					'code' => '54',
					'flag' => '🇦🇷',
				],
				[
					'name' => 'Armenia',
					'code' => '374',
					'flag' => '🇦🇲',
				],
				[
					'name' => 'Aruba',
					'code' => '297',
					'flag' => '🇦🇼',
				],
				[
					'name' => 'Australia',
					'code' => '61',
					'flag' => '🇦🇺',
				],
				[
					'name' => 'Austria',
					'code' => '43',
					'flag' => '🇦🇹',
				],
				[
					'name' => 'Azerbaijan',
					'code' => '994',
					'flag' => '🇦🇿',
				],
				[
					'name' => 'Bahrain',
					'code' => '973',
					'flag' => '🇧🇭',
				],
				[
					'name' => 'Bangladesh',
					'code' => '880',
					'flag' => '🇧🇩',
				],
				[
					'name' => 'Barbados',
					'code' => '1246',
					'flag' => '🇧🇧',
				],
				[
					'name' => 'Belarus',
					'code' => '375',
					'flag' => '🇧🇾',
				],
				[
					'name' => 'Belgium',
					'code' => '32',
					'flag' => '🇧🇪',
				],
				[
					'name' => 'Belize',
					'code' => '501',
					'flag' => '🇧🇿',
				],
				[
					'name' => 'Benin',
					'code' => '229',
					'flag' => '🇧🇯',
				],
				[
					'name' => 'Bermuda',
					'code' => '1441',
					'flag' => '🇧🇲',
				],
				[
					'name' => 'Bhutan',
					'code' => '975',
					'flag' => '🇧🇹',
				],
				[
					'name' => 'Bolivia',
					'code' => '591',
					'flag' => '🇧🇴',
				],
				[
					'name' => 'Bonaire, Sint Eustatius and Saba',
					'code' => '599',
					'flag' => '🇧🇶',
				],
				[
					'name' => 'Bosnia and Herzegovina',
					'code' => '387',
					'flag' => '🇧🇦',
				],
				[
					'name' => 'Botswana',
					'code' => '267',
					'flag' => '🇧🇼',
				],
				[
					'name' => 'Bouvet Island',
					'code' => '0055',
					'flag' => '🇧🇻',
				],
				[
					'name' => 'Brazil',
					'code' => '55',
					'flag' => '🇧🇷',
				],
				[
					'name' => 'British Indian Ocean Territory',
					'code' => '246',
					'flag' => '🇮🇴',
				],
				[
					'name' => 'Brunei',
					'code' => '673',
					'flag' => '🇧🇳',
				],
				[
					'name' => 'Bulgaria',
					'code' => '359',
					'flag' => '🇧🇬',
				],
				[
					'name' => 'Burkina Faso',
					'code' => '226',
					'flag' => '🇧🇫',
				],
				[
					'name' => 'Burundi',
					'code' => '257',
					'flag' => '🇧🇮',
				],
				[
					'name' => 'Cambodia',
					'code' => '855',
					'flag' => '🇰🇭',
				],
				[
					'name' => 'Cameroon',
					'code' => '237',
					'flag' => '🇨🇲',
				],
				[
					'name' => 'Cape Verde',
					'code' => '238',
					'flag' => '🇨🇻',
				],
				[
					'name' => 'Cayman Islands',
					'code' => '1345',
					'flag' => '🇰🇾',
				],
				[
					'name' => 'Central African Republic',
					'code' => '236',
					'flag' => '🇨🇫',
				],
				[
					'name' => 'Chad',
					'code' => '235',
					'flag' => '🇹🇩',
				],
				[
					'name' => 'Chile',
					'code' => '56',
					'flag' => '🇨🇱',
				],
				[
					'name' => 'China',
					'code' => '86',
					'flag' => '🇨🇳',
				],
				[
					'name' => 'Christmas Island',
					'code' => '61',
					'flag' => '🇨🇽',
				],
				[
					'name' => 'Cocos (Keeling) Islands',
					'code' => '61',
					'flag' => '🇨🇨',
				],
				[
					'name' => 'Colombia',
					'code' => '57',
					'flag' => '🇨🇴',
				],
				[
					'name' => 'Comoros',
					'code' => '269',
					'flag' => '🇰🇲',
				],
				[
					'name' => 'Congo',
					'code' => '242',
					'flag' => '🇨🇬',
				],
				[
					'name' => 'Cook Islands',
					'code' => '682',
					'flag' => '🇨🇰',
				],
				[
					'name' => 'Costa Rica',
					'code' => '506',
					'flag' => '🇨🇷',
				],
				[
					'name' => 'Cote D\'Ivoire (Ivory Coast)',
					'code' => '225',
					'flag' => '🇨🇮',
				],
				[
					'name' => 'Croatia',
					'code' => '385',
					'flag' => '🇭🇷',
				],
				[
					'name' => 'Cuba',
					'code' => '53',
					'flag' => '🇨🇺',
				],
				[
					'name' => 'Curaçao',
					'code' => '599',
					'flag' => '🇨🇼',
				],
				[
					'name' => 'Cyprus',
					'code' => '357',
					'flag' => '🇨🇾',
				],
				[
					'name' => 'Czech Republic',
					'code' => '420',
					'flag' => '🇨🇿',
				],
				[
					'name' => 'Democratic Republic of the Congo',
					'code' => '243',
					'flag' => '🇨🇩',
				],
				[
					'name' => 'Denmark',
					'code' => '45',
					'flag' => '🇩🇰',
				],
				[
					'name' => 'Djibouti',
					'code' => '253',
					'flag' => '🇩🇯',
				],
				[
					'name' => 'Dominica',
					'code' => '1767',
					'flag' => '🇩🇲',
				],
				[
					'name' => 'Dominican Republic',
					'code' => '1809',
					'flag' => '🇩🇴',
				],
				[
					'name' => 'Dominican Republic',
					'code' => '1829',
					'flag' => '🇩🇴',
				],
				[
					'name' => 'East Timor',
					'code' => '670',
					'flag' => '🇹🇱',
				],
				[
					'name' => 'Ecuador',
					'code' => '593',
					'flag' => '🇪🇨',
				],
				[
					'name' => 'Egypt',
					'code' => '20',
					'flag' => '🇪🇬',
				],
				[
					'name' => 'El Salvador',
					'code' => '503',
					'flag' => '🇸🇻',
				],
				[
					'name' => 'Equatorial Guinea',
					'code' => '240',
					'flag' => '🇬🇶',
				],
				[
					'name' => 'Eritrea',
					'code' => '291',
					'flag' => '🇪🇷',
				],
				[
					'name' => 'Estonia',
					'code' => '372',
					'flag' => '🇪🇪',
				],
				[
					'name' => 'Ethiopia',
					'code' => '251',
					'flag' => '🇪🇹',
				],
				[
					'name' => 'Falkland Islands',
					'code' => '500',
					'flag' => '🇫🇰',
				],
				[
					'name' => 'Faroe Islands',
					'code' => '298',
					'flag' => '🇫🇴',
				],
				[
					'name' => 'Fiji Islands',
					'code' => '679',
					'flag' => '🇫🇯',
				],
				[
					'name' => 'Finland',
					'code' => '358',
					'flag' => '🇫🇮',
				],
				[
					'name' => 'France',
					'code' => '33',
					'flag' => '🇫🇷',
				],
				[
					'name' => 'French Guiana',
					'code' => '594',
					'flag' => '🇬🇫',
				],
				[
					'name' => 'French Polynesia',
					'code' => '689',
					'flag' => '🇵🇫',
				],
				[
					'name' => 'French Southern Territories',
					'code' => '262',
					'flag' => '🇹🇫',
				],
				[
					'name' => 'Gabon',
					'code' => '241',
					'flag' => '🇬🇦',
				],
				[
					'name' => 'Gambia The',
					'code' => '220',
					'flag' => '🇬🇲',
				],
				[
					'name' => 'Georgia',
					'code' => '995',
					'flag' => '🇬🇪',
				],
				[
					'name' => 'Germany',
					'code' => '49',
					'flag' => '🇩🇪',
				],
				[
					'name' => 'Ghana',
					'code' => '233',
					'flag' => '🇬🇭',
				],
				[
					'name' => 'Gibraltar',
					'code' => '350',
					'flag' => '🇬🇮',
				],
				[
					'name' => 'Greece',
					'code' => '30',
					'flag' => '🇬🇷',
				],
				[
					'name' => 'Greenland',
					'code' => '299',
					'flag' => '🇬🇱',
				],
				[
					'name' => 'Grenada',
					'code' => '1473',
					'flag' => '🇬🇩',
				],
				[
					'name' => 'Guadeloupe',
					'code' => '590',
					'flag' => '🇬🇵',
				],
				[
					'name' => 'Guam',
					'code' => '1671',
					'flag' => '🇬🇺',
				],
				[
					'name' => 'Guatemala',
					'code' => '502',
					'flag' => '🇬🇹',
				],
				[
					'name' => 'Guernsey and Alderney',
					'code' => '441481',
					'flag' => '🇬🇬',
				],
				[
					'name' => 'Guinea',
					'code' => '224',
					'flag' => '🇬🇳',
				],
				[
					'name' => 'GuineaBissau',
					'code' => '245',
					'flag' => '🇬🇼',
				],
				[
					'name' => 'Guyana',
					'code' => '592',
					'flag' => '🇬🇾',
				],
				[
					'name' => 'Haiti',
					'code' => '509',
					'flag' => '🇭🇹',
				],
				[
					'name' => 'Heard Island and McDonald Islands',
					'code' => '672',
					'flag' => '🇭🇲',
				],
				[
					'name' => 'Honduras',
					'code' => '504',
					'flag' => '🇭🇳',
				],
				[
					'name' => 'Hong Kong S.A.R.',
					'code' => '852',
					'flag' => '🇭🇰',
				],
				[
					'name' => 'Hungary',
					'code' => '36',
					'flag' => '🇭🇺',
				],
				[
					'name' => 'Iceland',
					'code' => '354',
					'flag' => '🇮🇸',
				],
				[
					'name' => 'India',
					'code' => '91',
					'flag' => '🇮🇳',
				],
				[
					'name' => 'Indonesia',
					'code' => '62',
					'flag' => '🇮🇩',
				],
				[
					'name' => 'Iran',
					'code' => '98',
					'flag' => '🇮🇷',
				],
				[
					'name' => 'Iraq',
					'code' => '964',
					'flag' => '🇮🇶',
				],
				[
					'name' => 'Ireland',
					'code' => '353',
					'flag' => '🇮🇪',
				],
				[
					'name' => 'Israel',
					'code' => '972',
					'flag' => '🇮🇱',
				],
				[
					'name' => 'Italy',
					'code' => '39',
					'flag' => '🇮🇹',
				],
				[
					'name' => 'Jamaica',
					'code' => '1876',
					'flag' => '🇯🇲',
				],
				[
					'name' => 'Japan',
					'code' => '81',
					'flag' => '🇯🇵',
				],
				[
					'name' => 'Jersey',
					'code' => '441534',
					'flag' => '🇯🇪',
				],
				[
					'name' => 'Jordan',
					'code' => '962',
					'flag' => '🇯🇴',
				],
				[
					'name' => 'Kazakhstan',
					'code' => '7',
					'flag' => '🇰🇿',
				],
				[
					'name' => 'Kenya',
					'code' => '254',
					'flag' => '🇰🇪',
				],
				[
					'name' => 'Kiribati',
					'code' => '686',
					'flag' => '🇰🇮',
				],
				[
					'name' => 'Kosovo',
					'code' => '383',
					'flag' => '🇽🇰',
				],
				[
					'name' => 'Kuwait',
					'code' => '965',
					'flag' => '🇰🇼',
				],
				[
					'name' => 'Kyrgyzstan',
					'code' => '996',
					'flag' => '🇰🇬',
				],
				[
					'name' => 'Laos',
					'code' => '856',
					'flag' => '🇱🇦',
				],
				[
					'name' => 'Latvia',
					'code' => '371',
					'flag' => '🇱🇻',
				],
				[
					'name' => 'Lebanon',
					'code' => '961',
					'flag' => '🇱🇧',
				],
				[
					'name' => 'Lesotho',
					'code' => '266',
					'flag' => '🇱🇸',
				],
				[
					'name' => 'Liberia',
					'code' => '231',
					'flag' => '🇱🇷',
				],
				[
					'name' => 'Libya',
					'code' => '218',
					'flag' => '🇱🇾',
				],
				[
					'name' => 'Liechtenstein',
					'code' => '423',
					'flag' => '🇱🇮',
				],
				[
					'name' => 'Lithuania',
					'code' => '370',
					'flag' => '🇱🇹',
				],
				[
					'name' => 'Luxembourg',
					'code' => '352',
					'flag' => '🇱🇺',
				],
				[
					'name' => 'Macau S.A.R.',
					'code' => '853',
					'flag' => '🇲🇴',
				],
				[
					'name' => 'Macedonia',
					'code' => '389',
					'flag' => '🇲🇰',
				],
				[
					'name' => 'Madagascar',
					'code' => '261',
					'flag' => '🇲🇬',
				],
				[
					'name' => 'Malawi',
					'code' => '265',
					'flag' => '🇲🇼',
				],
				[
					'name' => 'Malaysia',
					'code' => '60',
					'flag' => '🇲🇾',
				],
				[
					'name' => 'Maldives',
					'code' => '960',
					'flag' => '🇲🇻',
				],
				[
					'name' => 'Mali',
					'code' => '223',
					'flag' => '🇲🇱',
				],
				[
					'name' => 'Malta',
					'code' => '356',
					'flag' => '🇲🇹',
				],
				[
					'name' => 'Man (Isle of)',
					'code' => '441624',
					'flag' => '🇮🇲',
				],
				[
					'name' => 'Marshall Islands',
					'code' => '692',
					'flag' => '🇲🇭',
				],
				[
					'name' => 'Martinique',
					'code' => '596',
					'flag' => '🇲🇶',
				],
				[
					'name' => 'Mauritania',
					'code' => '222',
					'flag' => '🇲🇷',
				],
				[
					'name' => 'Mauritius',
					'code' => '230',
					'flag' => '🇲🇺',
				],
				[
					'name' => 'Mayotte',
					'code' => '262',
					'flag' => '🇾🇹',
				],
				[
					'name' => 'Mexico',
					'code' => '52',
					'flag' => '🇲🇽',
				],
				[
					'name' => 'Micronesia',
					'code' => '691',
					'flag' => '🇫🇲',
				],
				[
					'name' => 'Moldova',
					'code' => '373',
					'flag' => '🇲🇩',
				],
				[
					'name' => 'Monaco',
					'code' => '377',
					'flag' => '🇲🇨',
				],
				[
					'name' => 'Mongolia',
					'code' => '976',
					'flag' => '🇲🇳',
				],
				[
					'name' => 'Montenegro',
					'code' => '382',
					'flag' => '🇲🇪',
				],
				[
					'name' => 'Montserrat',
					'code' => '1664',
					'flag' => '🇲🇸',
				],
				[
					'name' => 'Morocco',
					'code' => '212',
					'flag' => '🇲🇦',
				],
				[
					'name' => 'Mozambique',
					'code' => '258',
					'flag' => '🇲🇿',
				],
				[
					'name' => 'Myanmar',
					'code' => '95',
					'flag' => '🇲🇲',
				],
				[
					'name' => 'Namibia',
					'code' => '264',
					'flag' => '🇳🇦',
				],
				[
					'name' => 'Nauru',
					'code' => '674',
					'flag' => '🇳🇷',
				],
				[
					'name' => 'Nepal',
					'code' => '977',
					'flag' => '🇳🇵',
				],
				[
					'name' => 'Netherlands',
					'code' => '31',
					'flag' => '🇳🇱',
				],
				[
					'name' => 'New Caledonia',
					'code' => '687',
					'flag' => '🇳🇨',
				],
				[
					'name' => 'New Zealand',
					'code' => '64',
					'flag' => '🇳🇿',
				],
				[
					'name' => 'Nicaragua',
					'code' => '505',
					'flag' => '🇳🇮',
				],
				[
					'name' => 'Niger',
					'code' => '227',
					'flag' => '🇳🇪',
				],
				[
					'name' => 'Nigeria',
					'code' => '234',
					'flag' => '🇳🇬',
				],
				[
					'name' => 'Niue',
					'code' => '683',
					'flag' => '🇳🇺',
				],
				[
					'name' => 'Norfolk Island',
					'code' => '672',
					'flag' => '🇳🇫',
				],
				[
					'name' => 'North Korea',
					'code' => '850',
					'flag' => '🇰🇵',
				],
				[
					'name' => 'Northern Mariana Islands',
					'code' => '1670',
					'flag' => '🇲🇵',
				],
				[
					'name' => 'Norway',
					'code' => '47',
					'flag' => '🇳🇴',
				],
				[
					'name' => 'Oman',
					'code' => '968',
					'flag' => '🇴🇲',
				],
				[
					'name' => 'Pakistan',
					'code' => '92',
					'flag' => '🇵🇰',
				],
				[
					'name' => 'Palau',
					'code' => '680',
					'flag' => '🇵🇼',
				],
				[
					'name' => 'Palestinian Territory Occupied',
					'code' => '970',
					'flag' => '🇵🇸',
				],
				[
					'name' => 'Panama',
					'code' => '507',
					'flag' => '🇵🇦',
				],
				[
					'name' => 'Papua new Guinea',
					'code' => '675',
					'flag' => '🇵🇬',
				],
				[
					'name' => 'Paraguay',
					'code' => '595',
					'flag' => '🇵🇾',
				],
				[
					'name' => 'Peru',
					'code' => '51',
					'flag' => '🇵🇪',
				],
				[
					'name' => 'Philippines',
					'code' => '63',
					'flag' => '🇵🇭',
				],
				[
					'name' => 'Pitcairn Island',
					'code' => '870',
					'flag' => '🇵🇳',
				],
				[
					'name' => 'Poland',
					'code' => '48',
					'flag' => '🇵🇱',
				],
				[
					'name' => 'Portugal',
					'code' => '351',
					'flag' => '🇵🇹',
				],
				[
					'name' => 'Puerto Rico',
					'code' => '1787',
					'flag' => '🇵🇷',
				],
				[
					'name' => 'Puerto Rico',
					'code' => '1939',
					'flag' => '🇵🇷',
				],
				[
					'name' => 'Qatar',
					'code' => '974',
					'flag' => '🇶🇦',
				],
				[
					'name' => 'Reunion',
					'code' => '262',
					'flag' => '🇷🇪',
				],
				[
					'name' => 'Romania',
					'code' => '40',
					'flag' => '🇷🇴',
				],
				[
					'name' => 'Russia',
					'code' => '7',
					'flag' => '🇷🇺',
				],
				[
					'name' => 'Rwanda',
					'code' => '250',
					'flag' => '🇷🇼',
				],
				[
					'name' => 'Saint Helena',
					'code' => '290',
					'flag' => '🇸🇭',
				],
				[
					'name' => 'Saint Kitts And Nevis',
					'code' => '1869',
					'flag' => '🇰🇳',
				],
				[
					'name' => 'Saint Lucia',
					'code' => '1758',
					'flag' => '🇱🇨',
				],
				[
					'name' => 'Saint Pierre and Miquelon',
					'code' => '508',
					'flag' => '🇵🇲',
				],
				[
					'name' => 'Saint Vincent And The Grenadines',
					'code' => '1784',
					'flag' => '🇻🇨',
				],
				[
					'name' => 'SaintBarthelemy',
					'code' => '590',
					'flag' => '🇧🇱',
				],
				[
					'name' => 'SaintMartin (French part)',
					'code' => '590',
					'flag' => '🇲🇫',
				],
				[
					'name' => 'Samoa',
					'code' => '685',
					'flag' => '🇼🇸',
				],
				[
					'name' => 'San Marino',
					'code' => '378',
					'flag' => '🇸🇲',
				],
				[
					'name' => 'Sao Tome and Principe',
					'code' => '239',
					'flag' => '🇸🇹',
				],
				[
					'name' => 'Saudi Arabia',
					'code' => '966',
					'flag' => '🇸🇦',
				],
				[
					'name' => 'Senegal',
					'code' => '221',
					'flag' => '🇸🇳',
				],
				[
					'name' => 'Serbia',
					'code' => '381',
					'flag' => '🇷🇸',
				],
				[
					'name' => 'Seychelles',
					'code' => '248',
					'flag' => '🇸🇨',
				],
				[
					'name' => 'Sierra Leone',
					'code' => '232',
					'flag' => '🇸🇱',
				],
				[
					'name' => 'Singapore',
					'code' => '65',
					'flag' => '🇸🇬',
				],
				[
					'name' => 'Sint Maarten (Dutch part)',
					'code' => '1721',
					'flag' => '🇸🇽',
				],
				[
					'name' => 'Slovakia',
					'code' => '421',
					'flag' => '🇸🇰',
				],
				[
					'name' => 'Slovenia',
					'code' => '386',
					'flag' => '🇸🇮',
				],
				[
					'name' => 'Solomon Islands',
					'code' => '677',
					'flag' => '🇸🇧',
				],
				[
					'name' => 'Somalia',
					'code' => '252',
					'flag' => '🇸🇴',
				],
				[
					'name' => 'South Africa',
					'code' => '27',
					'flag' => '🇿🇦',
				],
				[
					'name' => 'South Georgia',
					'code' => '500',
					'flag' => '🇬🇸',
				],
				[
					'name' => 'South Korea',
					'code' => '82',
					'flag' => '🇰🇷',
				],
				[
					'name' => 'South Sudan',
					'code' => '211',
					'flag' => '🇸🇸',
				],
				[
					'name' => 'Spain',
					'code' => '34',
					'flag' => '🇪🇸',
				],
				[
					'name' => 'Sri Lanka',
					'code' => '94',
					'flag' => '🇱🇰',
				],
				[
					'name' => 'Sudan',
					'code' => '249',
					'flag' => '🇸🇩',
				],
				[
					'name' => 'Suriname',
					'code' => '597',
					'flag' => '🇸🇷',
				],
				[
					'name' => 'Svalbard And Jan Mayen Islands',
					'code' => '47',
					'flag' => '🇸🇯',
				],
				[
					'name' => 'Swaziland',
					'code' => '268',
					'flag' => '🇸🇿',
				],
				[
					'name' => 'Sweden',
					'code' => '46',
					'flag' => '🇸🇪',
				],
				[
					'name' => 'Switzerland',
					'code' => '41',
					'flag' => '🇨🇭',
				],
				[
					'name' => 'Syria',
					'code' => '963',
					'flag' => '🇸🇾',
				],
				[
					'name' => 'Taiwan',
					'code' => '886',
					'flag' => '🇹🇼',
				],
				[
					'name' => 'Tajikistan',
					'code' => '992',
					'flag' => '🇹🇯',
				],
				[
					'name' => 'Tanzania',
					'code' => '255',
					'flag' => '🇹🇿',
				],
				[
					'name' => 'Thailand',
					'code' => '66',
					'flag' => '🇹🇭',
				],
				[
					'name' => 'The Bahamas',
					'code' => '1242',
					'flag' => '🇧🇸',
				],
				[
					'name' => 'Togo',
					'code' => '228',
					'flag' => '🇹🇬',
				],
				[
					'name' => 'Tokelau',
					'code' => '690',
					'flag' => '🇹🇰',
				],
				[
					'name' => 'Tonga',
					'code' => '676',
					'flag' => '🇹🇴',
				],
				[
					'name' => 'Trinidad And Tobago',
					'code' => '1868',
					'flag' => '🇹🇹',
				],
				[
					'name' => 'Tunisia',
					'code' => '216',
					'flag' => '🇹🇳',
				],
				[
					'name' => 'Turkey',
					'code' => '90',
					'flag' => '🇹🇷',
				],
				[
					'name' => 'Turkmenistan',
					'code' => '993',
					'flag' => '🇹🇲',
				],
				[
					'name' => 'Turks And Caicos Islands',
					'code' => '1649',
					'flag' => '🇹🇨',
				],
				[
					'name' => 'Tuvalu',
					'code' => '688',
					'flag' => '🇹🇻',
				],
				[
					'name' => 'Uganda',
					'code' => '256',
					'flag' => '🇺🇬',
				],
				[
					'name' => 'Ukraine',
					'code' => '380',
					'flag' => '🇺🇦',
				],
				[
					'name' => 'United Arab Emirates',
					'code' => '971',
					'flag' => '🇦🇪',
				],
				[
					'name' => 'United Kingdom',
					'code' => '44',
					'flag' => '🇬🇧',
				],
				[
					'name' => 'United States / Canada',
					'code' => '1',
					'flag' => '🇺🇸',
				],
				[
					'name' => 'Uruguay',
					'code' => '598',
					'flag' => '🇺🇾',
				],
				[
					'name' => 'Uzbekistan',
					'code' => '998',
					'flag' => '🇺🇿',
				],
				[
					'name' => 'Vanuatu',
					'code' => '678',
					'flag' => '🇻🇺',
				],
				[
					'name' => 'Vatican City State (Holy See)',
					'code' => '379',
					'flag' => '🇻🇦',
				],
				[
					'name' => 'Venezuela',
					'code' => '58',
					'flag' => '🇻🇪',
				],
				[
					'name' => 'Vietnam',
					'code' => '84',
					'flag' => '🇻🇳',
				],
				[
					'name' => 'Virgin Islands (British)',
					'code' => '1284',
					'flag' => '🇻🇬',
				],
				[
					'name' => 'Virgin Islands (US)',
					'code' => '1340',
					'flag' => '🇻🇮',
				],
				[
					'name' => 'Wallis And Futuna Islands',
					'code' => '681',
					'flag' => '🇼🇫',
				],
				[
					'name' => 'Western Sahara',
					'code' => '212',
					'flag' => '🇪🇭',
				],
				[
					'name' => 'Yemen',
					'code' => '967',
					'flag' => '🇾🇪',
				],
				[
					'name' => 'Zambia',
					'code' => '260',
					'flag' => '🇿🇲',
				],
				[
					'name' => 'Zimbabwe',
					'code' => '263',
					'flag' => '🇿🇼',
				],
			];

			// map only name, phone_code, emoji.
			$countries = array_map( function ( $country ) {
				return [
					'name' => isset( $country['name'] ) ? $country['name'] : '',
					'code'  => isset( $country['code'] ) ? $country['code'] : '',
					'flag'  => isset( $country['flag'] ) ? $country['flag'] : '',
				];
			}, $countries );

			return apply_filters( 'scf_countries', $countries );
		}

		/**
		 * Embed fonts.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function embed_fonts() {
			$fonts = self::fonts();

			if ( ! is_array( $fonts ) || empty( $fonts ) ) {
				return '';
			}

			$families = [];
			foreach ( array_keys( $fonts ) as $font_family ) {
				$font_family = trim( (string) $font_family );
				if ( '' === $font_family || 'sans-serif' === strtolower( $font_family ) ) {
					continue;
				}

				$families[] = str_replace( ' ', '+', $font_family ) . ':wght@400;500;600;700';
			}

			if ( empty( $families ) ) {
				return '';
			}

			$import_url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', array_unique( $families ) ) . '&display=swap';
			$css        = "@import url('" . esc_url_raw( $import_url ) . "');";

			$css = apply_filters( 'scf_fonts_css', $css );
			return $css;
		}

		/**
		 * Widget Configuration blueprint.
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public static function widget_config() {
			$configuration = [
				'whatsapp' => [
					'web_version' => true,
					'country_code' => '',
					'number' => '',
					'message_template' =>
						'Name: {name}
Email: {email}
Phone: {phone}
Message: {message}',
					'cf7_message_template' => '',
					'fluentform_message_template' => '',
					'gravity_message_template' => '',
					'wpforms_message_template' => '',
					'formidable_message_template' => '',
					'ninja_message_template' => '',

					'new_tab' => true,
					'agent_mode' => 'single',
					'agents' => [],
					'on_click_agent' => 'show_form',
				],
				'icon' => [
					'has_delay' => false,
					'delay' => 3,
					'image_url' => FORMYCHAT_PUBLIC . '/images/whatsapp.svg',
					'size' => 'medium',
					'size_custom' => 80,
					'position' => 'right',
					'position_custom' => [
						'top' => '',
						'bottom' => '',
						'left' => '',
						'right' => '',
					],

					'show_after_scroll' => false,
					'scroll_to' => 25,
					'show_on_exit' => false,
				],
				'cta' => [
					'enabled' => true,
					'text' => 'Contact us',
					'size' => 'medium',
					'size_custom' => 20,
					'color' => '#555555',
					'background_color' => '#FFFFFF',
				],
				'form' => [
					'mode' => 'formychat',
					'title' => 'Contact via WhatsApp',
					'subtitle' => '',
					'footer' => '',
					'submit' => 'Send on WhatsApp',
					'show_country_code_field' => false,
					'country_code' => '44',
					'size' => 'medium',
					'size_custom' => '',
					'font_family' => 'sans-serif',
					'text_color' => '#ffffff',
					'background_color' => '#09816D',
					'open_by_default' => false,
					'close_on_submit' => true,

					// Third party forms.
					'cf7_id' => 0,
					'gravity_id' => 0,
					'wpforms_id' => 0,
					'fluentform_id' => 0,
					'forminator_id' => 0,
					'formidable_id' => 0,
					'ninja_id' => 0,
				],
				'email' => [
					'enabled' => false,
					'address' => '',
					'admin_email' => true,
				],
				'target' => [
					'exclude_pages' => [],
					'exclude_all_pages' => false,
					'exclude_all_pages_except' => [],
				],
				'greetings' => [
					'enabled' => false,
					'template' => 'simple',
					'style' => 1,
					'on_click' => 'show_form',
					'templates' => [
						'simple' => [
							'heading' => '👋 Hi! Have any queries?',
							'heading_size' => 'medium',
							'heading_size_custom' => 16,
							'heading_color' => '#828282',
							'message' => 'Feel free to ask your queries here. We are always ready to assist you anytime.',
							'message_size' => 'medium',
							'message_size_custom' => 16,
							'message_color' => '#4F4F4F',
							'background_color' => '#FFFFFF',
							'font_family' => 'sans-serif',
						],
						'wave' => [
							'show_icon' => true,
							'icon_url' => FORMYCHAT_PUBLIC . '/images/greetings/hand-wave.svg',
							'icon_url_custom' => '',
							'icon_position' => 'before_heading',
							'heading' => 'What are you looking for?',
							'heading_size' => 'medium',
							'heading_size_custom' => 16,
							'heading_color' => '#4F4F4F',
							'message' => 'Feel free to ask your questions here. We are always ready to assist you all the time whenever you need',
							'message_size' => 'medium',
							'message_size_custom' => 16,
							'message_color' => '#4F4F4F',
							'background_color' => '#FFFFFF',
							'font_family' => 'sans-serif',
							'show_cta' => true,
							'cta_text' => 'Ask your question',
							'cta_heading' => 'What are you looking for?',
							'cta_message' => 'Feel free to ask your questions here. We are always ready to assist you all the time whenever you need',
							'cta_icon_url' => FORMYCHAT_PUBLIC . '/images/greetings/vector.svg',
							'cta_icon_url_custom' => '',
							'cta_text_color' => '#2F80ED',
							'cta_background_color' => '#F5F7F8',
							'cta_heading_color' => '#2F80ED',
							'cta_message_color' => '#4F4F4F',
							'cta_heading_size' => 'medium',
							'cta_heading_size_custom' => 16,
							'cta_message_size' => 'medium',
							'cta_message_size_custom' => 16,
						],
					],
				],
			];

			return apply_filters( 'formychat_widget_configuration', $configuration );
		}

		/**
		 * Custom Tags.
		 *
		 * @since 1.0.0
		 * @return array
		 */
		public static function custom_tags() {
			$tags = [
				'site_name' => get_bloginfo( 'name' ),
				'site_url' => get_site_url(),
				'user_id' => get_current_user_id(),
				'page_url' => '',
				'page_id' => get_the_ID(),
			];

			return apply_filters( 'formychat_custom_tags', $tags );
		}

		/**
		 * Get all forms.
		 *
		 * @return array
		 */
		public static function get_forms() {
			$forms = [
				'formychat' => [
					'label' => 'FormyChat',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/formychat.png',
				],
				'cf7' => [
					'label' => 'Contact Form 7',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/contact-form-7.png',
				],
				'gravity' => [
					'label' => 'Gravity Forms',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/gravity-forms.png',
				],
				'wpforms' => [
					'label' => 'WP Forms',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/wp-forms.png',
				],
				'fluentform' => [
					'label' => 'Fluent Forms',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/fluent-form.png',
				],
				'forminator' => [
					'label' => 'Forminator',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/forminator.png',
				],
				'formidable' => [
					'label' => 'Formidable',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/formidable.png',
				],

				'ninja' => [
					'label' => 'Ninja Forms',
					'logo' => FORMYCHAT_PUBLIC . '/images/forms/ninja-forms.png',
				],
			];

			return apply_filters( 'formychat_forms', $forms );
		}



		/**
		 * FormyChat Form Fields.
		 *
		 * @since 1.0.0
		 */
		public static function form_fields() {

			$user = wp_get_current_user();
			$default_values = [];

			if ( $user->exists() ) {
				$default_values['name'] = $user->display_name;
				$default_values['email'] = $user->user_email;
				$default_values['phone'] = get_user_meta( $user->ID, 'billing_phone', true );
			}

			$fields = [
				[
					'name' => 'name',
					'type' => 'text',
					'default' => array_key_exists( 'name', $default_values ) ? $default_values['name'] : '',
					'help_text' => '',
					'attributes' => [
						'placeholder' => __( 'Enter your Name', 'social-contact-form' ),
						'required' => true,
					],
				],
				[
					'name' => 'email',
					'type' => 'email',
					'default' => array_key_exists( 'email', $default_values ) ? $default_values['email'] : '',
					'help_text' => '',
					'attributes' => [
						'placeholder' => __( 'Enter your Email', 'social-contact-form' ),
						'required' => true,
					],
					'condition' => 'name != ""',
				],
				[
					'name' => 'phone',
					'type' => 'phone',
					'default' => array_key_exists( 'phone', $default_values ) ? $default_values['phone'] : '',
					'help_text' => '',
					'attributes' => [
						'placeholder' => __( 'Enter your Phone', 'social-contact-form' ),
						'required' => true,
						'minlength' => 7,
						'maxlength' => 15,
						'min' => '100000',
						'max' => '999999999999999',
					],
				],
				[
					'name' => 'message',
					'type' => 'textarea',
					'help_text' => '',
					'attributes' => [
						'placeholder' => __( 'Enter your Message', 'social-contact-form' ),
						'required' => false,
					],
				],
			];

			return apply_filters( 'formychat_form_fields', $fields );
		}
	}
}



// If function formychat() doesn't exist, create it.
if ( ! function_exists( '\formychat' ) ) {
	/**
	 * FormyChat function.
	 *
	 * @since 1.0.0
	 * @return mixed
	 */
	function formychat() {
		// Get the instance of the FormyChat class.
		return new \FormyChat\App();
	}
}
