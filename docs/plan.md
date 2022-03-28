What we really want here is a single command something like this:

rewrite-composer [moodledir] [outputdir]

which does something like this:

* Find each component
* For each file in the component (excluding subplugins and other components within the component directory):
    * if it's not a PHP file, copy it into place in the corresponding composer directory
    * if it's a PHP file, rewrite it and copy it into the composer directory
* Create a composer.json file for the component, which must include the original relative Moodle path to the component, e.g. in extra.moodle.originalPath