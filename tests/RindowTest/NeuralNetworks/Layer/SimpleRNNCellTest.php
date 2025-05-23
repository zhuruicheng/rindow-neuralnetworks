<?php
namespace RindowTest\NeuralNetworks\Layer\SimpleRNNCellTest;

use PHPUnit\Framework\TestCase;
use Rindow\Math\Matrix\MatrixOperator;
use Rindow\NeuralNetworks\Backend\RindowBlas\Backend;
use Rindow\NeuralNetworks\Builder\NeuralNetworks;
use Rindow\NeuralNetworks\Layer\SimpleRNNCell;
use InvalidArgumentException;
use Interop\Polite\Math\Matrix\NDArray;
use Rindow\NeuralNetworks\Activation\Tanh;

class SimpleRNNCellTest extends TestCase
{
    public function newMatrixOperator()
    {
        return new MatrixOperator();
    }

    public function newNeuralNetworks($mo)
    {
        return new NeuralNetworks($mo);
    }

    public function verifyGradient($mo, $K, $function, NDArray $x,array $states)
    {
        $f = function($x) use ($mo,$K,$function,$states){
            $x = $K->array($x);
            $object = new \stdClass();
            $states = $function->forward($x,$states,calcState:$object);
            return $K->ndarray($states[0]);
        };
        $grads = $mo->la()->numericalGradient(1e-3,$f,$K->ndarray($x));
        $object = new \stdClass();
        $next_states = $function->forward($x,$states,calcState:$object);
        $dNextStates = [$K->ones($next_states[0]->shape(),$next_states[0]->dtype())];
        [$dInputs,$dPrevStates] = $function->backward($dNextStates,$object);

        return $mo->la()->isclose($grads[0],$K->ndarray($dInputs),1e-4);
    }

    public function testDefaultInitialize()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $layer = new SimpleRNNCell(
            $K,
            $units=4,
            input_shape:[3]
            );

        $layer->build([3]);
        $params = $layer->getParams();
        $this->assertCount(3,$params);
        $this->assertEquals([3,4],$params[0]->shape());
        $this->assertEquals([4,4],$params[1]->shape());
        $this->assertEquals([4],$params[2]->shape());

        $grads = $layer->getGrads();
        $this->assertCount(3,$grads);
        $this->assertEquals([3,4],$grads[0]->shape());
        $this->assertEquals([4,4],$grads[1]->shape());
        $this->assertEquals([4],$grads[2]->shape());
        $this->assertInstanceOf(
            Tanh::class, $layer->getActivation()
        );

        //$this->assertEquals([3],$layer->inputShape());
        $this->assertEquals([4],$layer->outputShape());
    }

    public function testSetInputShape()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $layer = new SimpleRNNCell(
            $K,
            $units=4,
            );
        $layer->build($inputShape=[3]);

        //$this->assertEquals([3],$layer->inputShape());
        $this->assertEquals([4],$layer->outputShape());
    }

    public function testUnmatchSpecifiedInputShape()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $layer = new SimpleRNNCell(
            $K,
            $units=4,
            input_shape:[3],
            );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Input shape is inconsistent: defined as (3) but (4) given in SimpleRNNCell');
        $layer->build([4]);
    }

    public function testNormalForwardAndBackward()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $fn = $K;

        $layer = new SimpleRNNCell(
            $K,
            $units=4,
            input_shape:[3]
            );

        $layer->build([3]);
        $grads = $layer->getGrads();


        //
        // forward
        //
        //  2 batch
        $inputs = $K->ones([2,3]);
        $states = [$K->ones([2,4])];
        $object = new \stdClass();
        $copyInputs = $K->copy($inputs);
        $copyStates = [$K->copy($states[0])];
        $nextStates = $layer->forward($inputs, $states,calcState:$object);
        //
        $this->assertCount(1,$nextStates);
        $this->assertEquals([2,4],$nextStates[0]->shape());
        $this->assertEquals($copyInputs->toArray(),$inputs->toArray());
        $this->assertEquals($copyStates[0]->toArray(),$states[0]->toArray());

        //
        // backward
        //
        // 2 batch
        $dStates =
            [$K->scale(2,$K->ones([2,4]))];

        $copydStates = [$K->copy($dStates[0])];
        [$dInputs,$dPrevStates] = $layer->backward($dStates,$object);
        // 2 batch
        $this->assertEquals([2,3],$dInputs->shape());
        $this->assertCount(1,$dPrevStates);
        $this->assertEquals([2,4],$dPrevStates[0]->shape());
        $this->assertNotEquals(
            $mo->zerosLike($grads[0])->toArray(),
            $grads[0]->toArray());
        $this->assertNotEquals(
            $mo->zerosLike($grads[1])->toArray(),
            $grads[1]->toArray());
        $this->assertNotEquals(
            $mo->zerosLike($grads[2])->toArray(),
            $grads[2]->toArray());

        $this->assertEquals($copydStates[0]->toArray(),$dStates[0]->toArray());
    }

    public function testOutputsAndGrads()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $fn = $K;

        $layer = new SimpleRNNCell(
            $K,
            $units=4,
            input_shape:[3],
            activation:'linear',
            );

        $kernel = $K->ones([3,4]);
        $recurrent = $K->ones([4,4]);
        $bias = $K->ones([4]);
        $layer->build([3],
            sampleWeights:[$kernel,$recurrent,$bias]
        );
        $this->assertNull($layer->getActivation());
        $grads = $layer->getGrads();


        //
        // forward
        //
        //  2 batch
        $inputs = $K->ones([2,3]);
        $states = [$K->ones([2,4])];
        $object = new \stdClass();
        $nextStates = $layer->forward($inputs, $states,calcState:$object);
        //
        $this->assertEquals([
            [8,8,8,8],
            [8,8,8,8],
            ],$nextStates[0]->toArray());
        //
        // backward
        //
        // 2 batch
        $dStates =
            [$K->scale(2,$K->ones([2,4]))];

        [$dInputs,$dPrevStates] = $layer->backward($dStates,$object);
        // 2 batch
        $this->assertEquals([
            [8,8,8],
            [8,8,8],
            ],$dInputs->toArray());
        $this->assertEquals([
            [8,8,8,8],
            [8,8,8,8],
            ],$dPrevStates[0]->toArray());
        $this->assertEquals([
            [4,4,4,4],
            [4,4,4,4],
            [4,4,4,4],
            ],$grads[0]->toArray());
        $this->assertEquals([
            [4,4,4,4],
            [4,4,4,4],
            [4,4,4,4],
            [4,4,4,4],
            ],$grads[1]->toArray());
        $this->assertEquals(
            [4,4,4,4]
            ,$grads[2]->toArray());
    }

    public function testVerifyGradient()
    {
        $mo = $this->newMatrixOperator();
        $nn = $this->newNeuralNetworks($mo);
        $K = $nn->backend();
        $g = $nn->gradient();
        $fn = $K;

        $layer = new SimpleRNNCell(
            $K,
            $units=3,
            input_shape:[10],
            #activation:'linear',
            );
        $layer->build([10]);
        $weights = $layer->getParams();

        $x = $K->array([
            [1],
        ],dtype:NDArray::int32);
        $states = [$K->zeros([1,3])];
        $object = new \stdClass();
        $x = $K->onehot($x->reshape([1]),$numClass=10)->reshape([1,10]);
        $outputs = $layer->forward($x,$states,calcState:$object);

        $this->assertTrue(
            $this->verifyGradient($mo,$K,$layer,$x,$states));
    }
}
