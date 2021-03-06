<?php defined('BASEPATH') OR exit('No direct script access allowed');

/* ==============================================================
 *
 * Java
 *
 * ==============================================================
 *
 * @copyright  2014 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('application/libraries/LanguageTask.php');

class Java_Task extends Task {
    public function __construct($source, $filename, $input, $params) {
        $params['memorylimit'] = 2500; // 2.5GB - JVM is greedy!
        $params['numprocs'] = 256;     // And Java 8 wants lots of processes
        Task::__construct($source, $filename, $input, $params);
        $this->default_params['interpreterargs'] = array(
             "-Xrs",   //  reduces usage signals by java, because that generates debug
                       //  output when program is terminated on timelimit exceeded.
             "-Xss8m",
             "-Xmx200m"
        );
        // Superclass constructor calls subclasses to get filename if it's
        // not provided, so $this->sourceFileName should now be set correctly.
        $extStart = strpos($this->sourceFileName, '.');  // Start of extension
        $this->mainClassName = substr($this->sourceFileName, 0, $extStart);
    }

    public static function getVersionCommand() {
        return array('java -version', '/openjdk version "([0-9._]*)"/');
    }

    public function compile() {
        $prog = file_get_contents($this->sourceFileName);
        $compileArgs = $this->getParam('compileargs');
        $cmd = '/usr/bin/javac ' . implode(' ', $compileArgs) . " {$this->sourceFileName} 2>compile.out";
        exec($cmd, $output, $returnVar);
        if ($returnVar == 0) {
            $this->executableFileName = $this->sourceFileName;
        }
        else {
            $this->cmpinfo .= file_get_contents('compile.out');
        }
    }

    // A default name for Java programs. [Called only if API-call does
    // not provide a filename]
    public function defaultFileName($sourcecode) {
        $main = $this->getMainClass($sourcecode);
        if ($main === FALSE) {
            $this->cmpinfo .= "WARNING: can't determine main class, so source file has been named 'prog.java', which probably won't compile.";
            return 'prog.java'; // This will probably fail
        } else {
            return $main.'.java';
        }
    }

    public function getExecutablePath() {
        return '/usr/bin/java';
    }



    public function getTargetFile() {
        return $this->mainClassName;
    }


    // Return the name of the main class in the given prog, or FALSE if no
    // such class found. Uses a regular expression to find a public class with
    // a public static void main method.
    // Not totally safe as it doesn't parse the file, e.g. would be fooled
    // by a commented-out main class with a different name.
    private function getMainClass($prog) {
        $pattern = '/(^|\W)public\s+class\s+(\w+)[^{]*\{.*?public\s+static\s+void\s+main\s*\(\s*String/ms';
        if (preg_match_all($pattern, $prog, $matches) !== 1) {
            return FALSE;
        }
        else {
            return $matches[2][0];
        }
    }

    // Get rid of the tab characters at the start of indented lines in
    // traceback output.
    public function filteredStderr() {
        return str_replace("\n\t", "\n        ", $this->stderr);
    }
};

