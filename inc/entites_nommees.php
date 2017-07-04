<?php

// TODO
// passer la liste des institutions automatique et personnalité dans une liste d'alias pour repecher les FARC par exemple.

include_spip('mots_courants.php');

// remonter la limite de taille d'une regexp
// essential for huge PCREs
// ini_set("pcre.backtrack_limit", "10000000000000");
// ini_set("pcre.recursion_limit", "10000000000000");

// http://fr.wikipedia.org/wiki/Table_des_caract%C3%A8res_Unicode/U0080
// http://www.regular-expressions.info/unicode.html
// To match a letter including any diacritics, use \p{L}\p{M}*+.
define("LETTRES","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒğı-]"); // il en manque, passer en /u
define("LETTRESAP","[a-zA-ZàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßœŒğı'-]"); // il en manque, passer en /u

// http://www.regular-expressions.info/unicode.html
/*

 \p{L} lettres
 \p{Lu} or \p{Uppercase_Letter}: an uppercase letter that has a lowercase variant.
 \p{Z} or \p{Separator}: any kind of whitespace or invisible separator.
      \p{Zs} or \p{Space_Separator}: a whitespace character that is invisible, but does take up space.
      \p{Zl} or \p{Line_Separator}: line separator character U+2028.
      \p{Zp} or \p{Paragraph_Separator}: paragraph separator character U+2029. 
    \p{N} or \p{Number}: any kind of numeric character in any script.
        \p{Nd} or \p{Decimal_Digit_Number}: a digit zero through nine in any script except ideographic scripts.
        \p{Nl} or \p{Letter_Number}: a number that looks like a letter, such as a Roman numeral.
        \p{No} or \p{Other_Number}: a superscript or subscript digit, or a number that is not a digit 0–9 (excluding numbers from ideographic scripts). 
    \p{P} or \p{Punctuation}: any kind of punctuation character.
        \p{Pd} or \p{Dash_Punctuation}: any kind of hyphen or dash.
        \p{Ps} or \p{Open_Punctuation}: any kind of opening bracket.
        \p{Pe} or \p{Close_Punctuation}: any kind of closing bracket.
        \p{Pi} or \p{Initial_Punctuation}: any kind of opening quote.
        \p{Pf} or \p{Final_Punctuation}: any kind of closing quote.
        \p{Pc} or \p{Connector_Punctuation}: a punctuation character such as an underscore that connects words.
        \p{Po} or \p{Other_Punctuation}: any kind of punctuation character that is not a dash, bracket, quote or connector. 
*/


define("LETTRE_CAPITALE","\p{Lu}");
