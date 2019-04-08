OpenMSA-1.2 transitional patch
==============================


OpenMSA-1.2 will be based on an updated .OVA
containing an new set of core php library files.

Most adaptors in this repository have been updated
to use new helper function `object_to_json()` which
was introduced as part of PR #27.

Adaptors in current `master` thus need a patched .OVA
to work properly.

Apply the patch below in your MSA VM.


	vi /opt/sms/bin/php/smsd/sms_common.php

	add:

	function object_to_json($object) {
		return json_encode($object, JSON_FORCE_OBJECT);
	}
