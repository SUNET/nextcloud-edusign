#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Mikael Nordin <kano@sunet.se>
# SPDX-License-Identifier: AGPL-3.0-or-later

set -euo pipefail

fail() {
	printf 'Release signing: %s\n' "$*" >&2
	exit 1
}

[[ $# -eq 4 ]] || fail 'Usage: sign-release.sh ARCHIVE APP_ID CERT_DIR IMAGE'
archive=$1
app_id=$2
cert_dir=$3
image=$4

[[ $app_id =~ ^[a-z][a-z0-9_-]*$ ]] || fail "Invalid app ID: $app_id"
[[ -n $image ]] || fail 'A signing image is required'
[[ -f $archive && -r $archive ]] || fail "Archive is not readable: $archive"
[[ -d $cert_dir ]] || fail "Certificate directory does not exist: $cert_dir"

for command in docker tar openssl mktemp id; do
	command -v "$command" >/dev/null || fail "Required command not found: $command"
done

archive_dir=$(cd -- "$(dirname -- "$archive")" && pwd -P)
archive="$archive_dir/$(basename -- "$archive")"
cert_dir=$(cd -- "$cert_dir" && pwd -P)
certificate="$cert_dir/$app_id.crt"
private_key="$cert_dir/$app_id.key"
[[ -f $certificate && -r $certificate ]] || fail "Certificate is not readable: $certificate"
[[ -f $private_key && -r $private_key ]] || fail "Private key is not readable: $private_key"

stage=$(mktemp -d "$archive_dir/sign-$app_id-XXXXXX")
trap 'rm -rf -- "$stage"' EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

tar -xzf "$archive" --no-same-owner -C "$stage"
[[ -f $stage/$app_id/appinfo/info.xml ]] || fail 'Archive does not contain the app metadata'
rm -f -- "$stage/$app_id/appinfo/signature.json"

# Use the image's complete core tree, not the asynchronously populated web root.
# The temporary config belongs to the same UID that runs occ and owns the app.
docker run --rm --network none --cap-drop ALL \
	--security-opt no-new-privileges \
	--user "$(id -u):$(id -g)" \
	--entrypoint /bin/sh --workdir /usr/src/nextcloud \
	--env NEXTCLOUD_CONFIG_DIR=/tmp/nextcloud-sign-config \
	--mount "type=bind,source=$stage/$app_id,target=/app" \
	--mount "type=bind,source=$certificate,target=/certificates/app.crt,readonly" \
	--mount "type=bind,source=$private_key,target=/certificates/app.key,readonly" \
	"$image" -ec '
		mkdir -p "$NEXTCLOUD_CONFIG_DIR"
		touch "$NEXTCLOUD_CONFIG_DIR/CAN_INSTALL"
		exec php /usr/src/nextcloud/occ integrity:sign-app \
			--certificate=/certificates/app.crt \
			--privateKey=/certificates/app.key --path=/app
	'

[[ -s $stage/$app_id/appinfo/signature.json ]] || fail 'Nextcloud did not produce an app signature'
tar -czf "$stage/signed.tar.gz" -C "$stage" "$app_id"
openssl dgst -sha512 -sign "$private_key" -out "$stage/archive.sig" "$stage/signed.tar.gz"
signature=$(openssl base64 -A -in "$stage/archive.sig")

# Both signatures must succeed before replacing the input archive.
mv -- "$stage/signed.tar.gz" "$archive"
printf 'App Store archive signature:\n%s\n' "$signature"
