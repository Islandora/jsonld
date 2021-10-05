# ![Islandora-JSON-LD](https://cloud.githubusercontent.com/assets/2371345/24964530/f054bddc-1f77-11e7-8b54-d04bb7b2281c.png) JSONLD
[![Build Status][1]](https://github.com/Islandora/jsonld/actions)
[![Contribution Guidelines][2]](./CONTRIBUTING.md)
[![LICENSE][3]](./LICENSE)

## Introduction

JSON-LD Serializer for Drupal 8 and Islandora.

This module adds a simple Drupal entity to JSON-LD 
`normalizer/serializer/unserializer` service provider and a few supporting 
classes. It depends on RDF module and existing fields to RDF properties 
mappings to do its job.

## Configuration

The JSON-LD normalizer adds a `?_format=jsonld` to all URIs by default.

You can disable this via a checkbox in the Configuration -> Search and Metadata -> JsonLD form.

## Maintainers

Current maintainers:

* [Jared Whiklo][4]

## Development

If you would like to contribute, please get involved by attending our weekly 
[Tech Call][5]. We love to hear from you!

If you would like to contribute code to the project, you need to be covered by 
an Islandora Foundation [Contributor License Agreement][6] or 
[Corporate Contributor License Agreement][7]. Please see the [Contributors][8]
 pages on Islandora.ca for more information.
 
 We recommend using the [islandora-playbook][9] to get started. 

## License

[GPLv2](http://www.gnu.org/licenses/gpl-2.0.txt)

[1]: https://github.com/islandora/jsonld/actions/workflows/build-2.x.yml/badge.svg
[2]: http://img.shields.io/badge/CONTRIBUTING-Guidelines-blue.svg
[3]: https://img.shields.io/badge/license-GPLv2-blue.svg?style=flat-square
[4]: https://github.com/whikloj
[5]: https://github.com/Islandora/documentation/wiki
[6]: http://islandora.ca/sites/default/files/islandora_cla.pdf
[7]: http://islandora.ca/sites/default/files/islandora_ccla.pdf
[8]: http://islandora.ca/resources/contributors
[9]: https://github.com/Islandora-Devops/islandora-playbook
