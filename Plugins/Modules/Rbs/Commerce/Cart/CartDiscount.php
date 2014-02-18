<?php
namespace Rbs\Commerce\Cart;

/**
* @name \Rbs\Commerce\Cart\CartDiscount
*/
class CartDiscount extends \Rbs\Commerce\Process\BaseDiscount implements \Serializable
{
	/**
	 * @var array|null
	 */
	protected $serializedData;

	/**
	 * @var \Rbs\Commerce\Cart\CartManager
	 */
	protected $cartManager;

	/**
	 * @return \Rbs\Commerce\Cart\CartManager
	 */
	protected function getCartManager()
	{
		return $this->cartManager;
	}

	/**
	 * @param \Rbs\Commerce\Cart\CartManager $cartManager
	 * @return $this
	 */
	public function setCartManager(\Rbs\Commerce\Cart\CartManager $cartManager)
	{
		$this->cartManager = $cartManager;
		if ($this->serializedData)
		{
			$this->restoreSerializedData();
		}
		return $this;
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize()
	{
		$serializedData = [
			'id' => $this->id,
			'title' => $this->title,
			'lineKeys' => $this->lineKeys,
			'price' => $this->price,
			'taxes' => $this->taxes,
			'options' => $this->options
		];
		return serialize($this->cartManager->getSerializableValue($serializedData));
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 */
	public function unserialize($serialized)
	{
		$this->serializedData = unserialize($serialized);
	}

	/**
	 * @return array
	 */
	protected function restoreSerializedData()
	{
		$serializedData = $this->getCartManager()->restoreSerializableValue($this->serializedData);
		$this->serializedData = null;

		$this->id = $serializedData['id'];
		$this->title = $serializedData['title'];
		$this->lineKeys = $serializedData['lineKeys'];
		$this->price = $serializedData['price'];
		$this->taxes = $serializedData['taxes'];
		$this->options = $serializedData['options'];

		return $serializedData;
	}
}