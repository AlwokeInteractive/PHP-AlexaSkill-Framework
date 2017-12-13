<?php

	class IntentController extends IntentView
	{
		/**
		 * 
		 * {@inheritDoc}
		 * @see IntentView::Compute()
		 */
		public function Launch()
		{
			$this->GetResponse()->AddOutput(new Speech("This will be executed when the App is launched with no Intent"))->KeepListening();
		}
		
		public function SomeIntentWithCard()
		{
			$card=new Card("Title","Text");
			$this->GetResponse()->AddOutput(new Speech("This will be executed on the Intent SomeIntent!"))->AddCard($card);
		}
		
		public function SomeIntentWithSlots()
		{
			if ($slotVal=$this->GetIntent()->GetSlotValue("SlotName"))
			{
				$this->GetResponse()->AddOutput(new Speech("This Request includes the Slot SlotName with the Value of $slotVal"));
			}
			else
			{
				$this->GetResponse()->AddOutput(new Speech("No Slot Value has been given!"));
			}
		}
		
		
		public function HelpIntent()
		{
			$this->GetResponse()->AddOutput(new Speech("This is a must and will be called if the User asks for Help."))->KeepListening();
		}
		
		public function CancelIntent()
		{
			$this->GetResponse()->AddOutput(new Speech("This is a must and is called when the users Cancels an Intent."));
		}
		
		public function StopIntent()
		{
			$this->GetResponse()->AddOutput(new Speech("This is a must and is called when the users Stops an Intent."));
		}
		
		public function Error()
		{
			$this->GetResponse()->AddOutput(new Speech("In case an Intent is called that does not exists as a function here, this function will be called instead!"));
		}
	}
	
?>