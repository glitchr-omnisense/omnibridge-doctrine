<?php

namespace Omnibridge\Doctrine\Omnibase\Database\Type;

/**
 * A type whose PHP value is not the one its column holds, so that a finder's criterion on its
 * field is bound with the type, as Doctrine's own findBy() binds it (plan "omnibase agnostic",
 * step E2): Passkey\Base64 keeps bytes as base64 text, Passkey\Aaguid an id as its text form.
 * Without it, findOneBy(['publicKeyCredentialId' => $bytes]) compared the bytes with the text.
 * Only equality (EqualTo, NotEqualTo) on one value is bound so; a list, a LIKE and the dates keep
 * the finder's own binding.
 */
interface ConvertedInCriteria
{
}
