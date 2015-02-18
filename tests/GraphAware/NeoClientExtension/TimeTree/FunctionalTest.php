<?php

use Neoxygen\NeoClient\ClientBuilder;
use GraphAware\NeoClientExtension\TimeTree\TimeTreeExtension;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Neoxygen\NeoClient\Client
     */
    protected $client;

    public function setUp()
    {
        $this->client = ClientBuilder::create()
            ->addDefaultLocalConnection()
            ->setAutoFormatResponse(true)
            ->registerExtension('graphaware_timetree', 'GraphAware\\NeoClientExtension\\TimeTree\\TimeTreeExtension')
            ->build();
    }
    public function testCreateIndexes()
    {
        $this->client->createTimeTreeIndexes();
        $this->assertTrue($this->client->isIndexed('Year', 'value'));
        $this->assertTrue($this->client->isIndexed('Millisecond', 'value'));
    }

    public function testGetTimeNode()
    {
        $dt = new \DateTime("NOW");
        $t = $dt->getTimestamp();

        $tnode = $this->client->getTimeNode($t);
        $this->assertInternalType('int', $tnode);
    }

    public function testGetTimeNodeForDayReturnsSameNode()
    {
        $dt = new \DateTime('NOW');
        $t = $dt->format('d');
        $first = $this->client->getTimeNode($t);
        $second = $this->client->getTimeNode($t);
        $this->assertSame($first, $second);
    }

    public function testGetTimeNodeWithResolution()
    {
        $dt = new \DateTime("NOW");
        $t = $dt->getTimestamp();
        $this->assertInternalType('int', $this->client->getTimeNode($t, TimeTreeExtension::TIMETREE_RESOLUTION_MILLISECOND));
    }

    public function testAttachNodeToTime()
    {
        $dt = new \DateTime("NOW");
        $t = $dt->getTimestamp();
        $this->client->sendCypherQuery('MATCH (n) OPTIONAL MATCH (n)-[r]-() DELETE r,n');
        $q = 'CREATE (n:SuperEvent) RETURN n';
        $result = $this->client->sendCypherquery($q)->getResult();
        $node = $result->get('n');
        $nodeId = $node->getId();

        $this->assertTrue($this->client->addNodeToTime($nodeId, $t, 'SUPER_EVENT_OCCURED_ON'));
        $event = $this->client->getTimeEvents($t)->getResult();
        $this->assertSame($nodeId, $event->getSingleNode()->getId());
    }

    public function testGetEventsInRange()
    {
        $this->client->sendCypherQuery('MATCH (n) OPTIONAL MATCH (n)-[r]-() DELETE r,n');
        $start = new \DateTime();
        $start->setDate(2015, 1, 31);
        $end = new \DateTime();
        $end->setDate(2015, 2, 18);
        $q = 'CREATE (n:SuperEvent),
        (n2:SuperEvent)
        RETURN n, n2';
        $r = $this->client->sendCypherQuery($q)->getResult();
        $startNodeId = $r->get('n')->getId();
        $endNodeId = $r->get('n2')->getId();
        $this->client->addNodeToTime($startNodeId, $start->getTimestamp(), 'EVENT_OCCURS_ON');
        $this->client->addNodeToTime($endNodeId, $end->getTimestamp(), 'EVENT_OCCURS_ON');
        $result = $this->client->getTimeEventsInRange($start->getTimestamp(), $end->getTimestamp())->getResult();

        $this->assertCount(2, $result->getNodes());

    }
}