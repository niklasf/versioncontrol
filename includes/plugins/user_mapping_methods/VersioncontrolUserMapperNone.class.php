<?php
//$Id

/**
 * Plugin that do not map.
 */
class VersioncontrolUserMapperSimpleMail implements VersioncontrolUserMapperInterface {
  public function mapAuthor(VersioncontrolOperation $commit) {
    return FALSE;
  }

  public function mapCommitter(VersioncontrolOperation $commit) {
    return FALSE;
  }
}
