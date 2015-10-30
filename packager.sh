#!/bin/bash
# packager script
# Takes current active branch and creates a package out of it

function get_git_current_branch() {
    echo $(git rev-parse --abbrev-ref HEAD)
}

mkdir api
# Current branch is used with packaging process
git archive $(get_git_current_branch) | tar -x -C api
rm api/packager.sh
zip -r "api.zip" api
rm -r api
