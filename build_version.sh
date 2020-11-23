#!/bin/sh
VERSION=$(cat version_semver)
HASH=$(cat version_hash)
BUILD_ID=$(cat version_build_id)
DATE=$(date -u +%Y-%m-%dT%H:%M:%S%z)
echo "$VERSION-$HASH-$BUILD_ID $DATE";
